<?php
return [
    'host'     => env('FTP_HOST', env('PHOTOS_HTTP_BASE', '')),
    'username' => env('FTP_USERNAME', ''),
    'password' => env('FTP_PASSWORD', ''),
    'port'     => env('FTP_PORT', env('FTP_PORT_DEFAULT', 21)),
    'root'     => env('FTP_ROOT', env('FTP_ROOT_DEFAULT', '/')),
    'passive'  => (bool) env('FTP_PASSIVE', true),
    'ssl'      => (bool) env('FTP_SSL', true),
    'timeout'  => (int) env('FTP_TIMEOUT', 30),
    // Публичный HTTP(S)-адрес файлов, если он отличается от FTP_HOST.
    'public_http_base' => env('PHOTO_PUBLIC_HTTP_BASE', env('PHOTOS_HTTP_BASE', '')),
];
