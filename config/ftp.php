<?php
return [
    'host'     => env('FTP_HOST', env('PHOTOS_HTTP_BASE', '')),
    'username' => env('FTP_USERNAME', ''),
    'password' => env('FTP_PASSWORD', ''),
    'port'     => env('FTP_PORT', 21),
    'root'     => env('FTP_ROOT', '/'),
    'passive'  => (bool) env('FTP_PASSIVE', true),
    'ssl'      => (bool) env('FTP_SSL', true),
    'timeout'  => (int) env('FTP_TIMEOUT', 30),
];
