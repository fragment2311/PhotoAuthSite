<?php

namespace App\Http\Controllers;

use App\Services\ErrorNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ThumbnailController extends Controller
{
    /**
     * Отдаёт миниатюру (PNG) по прямому запросу.
     *
     * @param  int    $width  Ширина миниатюры
     * @param  string $path   Путь к файлу внутри FTP-каталога
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, int $width): Response
    {
        $width = max(80, min($width, 640));
        $path = trim((string) $request->query('path', ''));
        abort_if($path === '', 404, 'Путь к фото не указан');

        // Миниатюра хранится под hash исходного URL: в имени кэша нет управляющих
        // символов или структуры удалённого хранилища.
        $disk = Storage::disk('public');
        $rawHost = trim((string) config('filesystems.disks.ftp.host', ''));
        if ($rawHost === '') {
            abort(404, 'Фото-хранилище недоступно');
        }

        $scheme = trim((string) config('app.photos_http_scheme', ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $scheme = '';
        }

        if ($rawHost !== '' && !preg_match('~^https?://~i', $rawHost)) {
            if ($scheme === '') {
                $appUrl = trim((string) config('app.url', ''));
                if (preg_match('~^https?://~i', $appUrl)) {
                    $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: '';
                }
            }

            if ($scheme === '') {
                $scheme = 'http';
            }

            $originalBase = $scheme . '://' . rtrim($rawHost, '/');
        } else {
            $originalBase = rtrim($rawHost, '/');
        }

        if ($originalBase === '') {
            abort(404, 'Фото-хранилище недоступно');
        }

        $originalUrl = $this->resolveOriginalUrl($path, $originalBase);
        $thumbKey = "thumbs/{$width}/" . sha1($originalUrl) . '.png';

        // 2) Если миниатюры ещё нет — скачиваем оригинал и создаём
        if (! $disk->exists($thumbKey)) {
            $lockName = 'thumb:build:' . sha1($thumbKey);
            $lock = Cache::lock($lockName, 15);

            if (! $lock->get()) {
                // кто-то уже генерирует — чуть подождем, чтобы избежать stampede
                $lock->block(5);
            }

            try {
                // пока ожидали, другой процесс мог уже сгенерировать
                if ($disk->exists($thumbKey)) {
                    $file = $disk->path($thumbKey);
                    $maxAge = 31536000;

                    return response()->file($file, [
                        'Content-Type'  => 'image/png',
                        'Cache-Control' => "public, max-age={$maxAge}, immutable",
                    ]);
                }

            // Оригинал уже нормализован и ограничен настроенным хранилищем.
            if ($disk->exists($thumbKey)) {
                $file = $disk->path($thumbKey);
                return response()->file($file, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            }

            $resp = Http::timeout(6)
                ->retry(1, 200)
                ->get($originalUrl);
            if (! $resp->ok()) {
                app(ErrorNotificationService::class)->log('warning', 'Оригинал фото недоступен для генерации миниатюры', [
                    'path' => $path,
                    'url' => $originalUrl,
                    'status' => $resp->status(),
                    'width' => $width,
                ]);

                abort(404, 'Оригинал не найден');
            }

            // создаём папку
            $dir = dirname($thumbKey);
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir, 0755, true);
            }

            // сжимаем и сохраняем миниатюру
            $imageManager = new ImageManager(new Driver());
            $thumbnail = $imageManager
                ->read($resp->body())
                ->scaleDown(width: $width)
                ->toPng();

            $disk->put($thumbKey, (string) $thumbnail);
            } catch (\Throwable $e) {
                app(ErrorNotificationService::class)->log('error', 'Ошибка генерации миниатюры', [
                    'path' => $path,
                    'width' => $width,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                throw $e;
            } finally {
                optional($lock)->release();
            }
        }

        // 3) Читаем готовый файл и отдаем его с нужными заголовками
        $file = $disk->path($thumbKey);
        $maxAge = 31536000;

        return response()->file($file, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => "public, max-age={$maxAge}, immutable",
        ]);
    }
    private function resolveOriginalUrl(string $path, string $originalBase): string
    {
        if (!preg_match('~^https?://~i', $path)) {
            return rtrim($originalBase, '/') . '/' . ltrim($path, '/');
        }

        $source = parse_url($path);
        $base = parse_url($originalBase);
        $sameHost = isset($source['host'], $base['host'])
            && strcasecmp((string) $source['host'], (string) $base['host']) === 0;
        $sourcePort = (int) ($source['port'] ?? 0);
        $basePort = (int) ($base['port'] ?? 0);

        // Регистратор сохраняет HTTP-ссылки, а сайт может работать по HTTPS.
        // Ограничение по хосту и порту не даёт использовать этот маршрут как proxy.
        abort_unless($sameHost && $sourcePort === $basePort, 404, 'Фото находится вне настроенного хранилища');

        return $path;
    }
}