<?php

namespace App\Console\Commands;

use App\Services\ErrorNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class GenerateThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'thumbnails:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-generates thumbnails for all images to speed up gallery loading';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting thumbnail generation...');

        $rawHost = trim((string) config('filesystems.disks.ftp.host', ''));
        if ($rawHost === '') {
            $this->warn('FTP host не задан, генерация миниатюр пропущена.');
            return self::SUCCESS;
        }

        $ftpDisk = Storage::disk('ftp');
        $publicDisk = Storage::disk('public');
        $widths = [240, 1024]; // Standard width for previews and a larger one for lightbox
        $rootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');
        if ($rootDir === '') {
            $this->warn('gallery_root_dir не задан, генерация миниатюр пропущена.');
            return self::SUCCESS;
        }

        // Get all image paths from the FTP storage
        try {
            $allFiles = $ftpDisk->allFiles($rootDir);
        } catch (\Throwable $e) {
            $this->warn('Не удалось получить список файлов из фотохранилища: ' . $e->getMessage());

            app(ErrorNotificationService::class)->log('critical', 'Ошибка получения списка файлов из фотохранилища', [
                'root_dir' => $rootDir,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return self::SUCCESS;
        }

        if (count($allFiles) === 0) {
            $this->info('Файлы для генерации миниатюр не найдены.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d files to process for %d sizes.', count($allFiles), count($widths)));

        $progressBar = $this->output->createProgressBar(count($allFiles));
        $progressBar->start();

        foreach ($allFiles as $path) {
            $progressBar->advance();

            if (!preg_match('/\.(jpe?g|png|gif)$/i', $path)) {
                continue;
            }

            foreach ($widths as $width) {
                $thumbKey = "thumbs/{$width}/{$path}";

                if ($publicDisk->exists($thumbKey)) {
                    continue;
                }

                try {
                    $fileContents = $ftpDisk->get($path);
                    if (!$fileContents) {
                        $this->warn("Could not read file: {$path}");
                        continue 2; // Continue to the next file
                    }

                    $dir = dirname($thumbKey);
                    if (!$publicDisk->exists($dir)) {
                        $publicDisk->makeDirectory($dir);
                    }

                    $imageManager = new ImageManager(new Driver());
                    $thumbnail = $imageManager
                        ->read($fileContents)
                        ->scaleDown(width: $width)
                        ->toPng();

                    $publicDisk->put($thumbKey, (string) $thumbnail);

                } catch (\Exception $e) {
                    $this->error("\nFailed to generate thumbnail for {$path} [{$width}px]: " . $e->getMessage());

                    app(ErrorNotificationService::class)->log('error', 'Ошибка генерации миниатюры в консольной команде', [
                        'path' => $path,
                        'width' => $width,
                        'error' => $e->getMessage(),
                        'exception' => $e,
                    ]);
                }
            }
        }

        $progressBar->finish();
        $this->info('\nThumbnail generation finished successfully.');
        return self::SUCCESS;
    }
}
