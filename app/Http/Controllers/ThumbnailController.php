<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Http\Response;
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
    public function show(int $width, string $path): Response
    {
        // 1) Где храним: public/thumbs/{width}/{path}
        //    (можно сменить на storage/app/public/thumbs/...)
        $disk      = Storage::disk('public');
        $thumbKey  = "thumbs/{$width}/{$path}";
        $rawHost = trim((string) config('filesystems.disks.ftp.host', ''));
        if ($rawHost !== '' && !preg_match('~^https?://~i', $rawHost)) {
            $originalBase = 'http://' . rtrim($rawHost, '/');
        } else {
            $originalBase = rtrim($rawHost !== '' ? $rawHost : (string) config('app.url', ''), '/');
        }

        // 2) Если миниатюры ещё нет — скачиваем оригинал и создаём
        if (! $disk->exists($thumbKey)) {
            // скачиваем оригинал
            $originalUrl = rtrim($originalBase, '/').'/'.ltrim($path, '/');
            $resp = Http::get($originalUrl);
            if (! $resp->ok()) {
                abort(404, 'Оригинал не найден');
            }

            // создаём папку
            $dir = dirname($thumbKey);
            if (! $disk->exists($dir)) {
                $disk->makeDirectory($dir, 0755, true);
            }

            // сжимаем и сохраняем миниатюру
            $img = Image::make($resp->body())
                        ->resize($width, null, function($c){
                            $c->aspectRatio();
                            $c->upsize();
                        })
                        ->encode('png', 80);

            $disk->put($thumbKey, (string)$img);
        }

        // 3) Читаем готовый файл и отдаем его с нужными заголовками
        $file = $disk->path($thumbKey);
        $maxAge = 31536000;

        return response()->file($file, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => "public, max-age={$maxAge}, immutable",
        ]);
    }
}
