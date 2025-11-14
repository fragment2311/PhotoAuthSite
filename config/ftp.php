<?php
return [
    'host'     => env('FTP_HOST', '192.168.9.134'),  // IP-адрес FTP-сервера
    'username' => env('FTP_USERNAME', 'skud'),  // Ваш FTP-логин
    'password' => env('FTP_PASSWORD', 'Qazxsw2!'),  // Ваш FTP-пароль
    'port'     => env('FTP_PORT', 21),  // Порт FTP-сервера (обычно 21 для обычного FTP)
];
