<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

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

        $ftpDisk = Storage::disk('ftp');
        $publicDisk = Storage::disk('public');
        $widths = [240, 1024]; // Standard width for previews and a larger one for lightbox
        $rootDir = trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');
        if ($rootDir === '') {
            $this->warn('gallery_root_dir не задан, генерация миниатюр пропущена.');
            return self::SUCCESS;
        }

        // Get all image paths from the FTP storage
        $allFiles = $ftpDisk->allFiles($rootDir);

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

                    $img = Image::make($fileContents)
                                ->resize($width, null, function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                                })
                                ->encode('png', 80);

                    $publicDisk->put($thumbKey, (string) $img);

                } catch (\Exception $e) {
                    $this->error("\nFailed to generate thumbnail for {$path} [{$width}px]: " . $e->getMessage());
                }
            }
        }

        $progressBar->finish();
        $this->info('\nThumbnail generation finished successfully.');
        return 0;
    }
}
