# Интеграция Telegram логгера в существующий код PhotoAuthApp

## 1. Обновление конфигурации

### 1.1 Добавление настроек в config/services.php
```php
<?php

return [
    // ... существующие настройки
    
    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
        'levels' => env('TELEGRAM_LEVELS', 'error,warning,critical,emergency'),
    ],
    
    // ... остальные настройки
];
```

### 1.2 Обновление .env файла
```
# Telegram настройки
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=8340634038:AAE-Fkstk8zpfkk_bybwRHH4P5ivX6yQo4I
TELEGRAM_CHAT_ID=-1001234567890
TELEGRAM_LEVELS=error,warning,critical,emergency
```

## 2. Расширение сервиса TelegramLogger

### 2.1 Обновленный класс TelegramLogger
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramLogger
{
    private string $botToken;
    private string $chatId;
    private array $levels;
    private bool $enabled;
    
    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id', '');
        $this->enabled = config('services.telegram.enabled', false);
        $this->levels = explode(',', config('services.telegram.levels', 'error,warning,critical,emergency'));
    }
    
    /**
     * Отправляет сообщение в Telegram
     *
     * @param string $message Текст сообщения
     * @param string $level Уровень ошибки (error, warning, info)
     * @param array $context Дополнительная информация
     * @return bool Успешность отправки
     */
    public function send(string $message, string $level = 'error', array $context = []): bool
    {
        // Проверяем, включена ли отправка в Telegram
        if (!$this->enabled || empty($this->botToken) || empty($this->chatId)) {
            return false;
        }
        
        // Проверяем, нужно ли отправлять сообщение данного уровня
        if (!in_array($level, $this->levels)) {
            return false;
        }
        
        try {
            // Формируем сообщение с информацией об окружении
            $fullMessage = $this->formatMessage($message, $level, $context);
            
            // Отправляем сообщение через Telegram API
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => $fullMessage,
                    'parse_mode' => 'HTML',
                ]);
                
            return $response->successful();
        } catch (\Exception $e) {
            // Логируем ошибку отправки в случае неудачи
            Log::error('Telegram logger error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Форматирует сообщение для отправки в Telegram
     */
    private function formatMessage(string $message, string $level, array $context): string
    {
        $levelIcon = match($level) {
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            'critical' => '🚨',
            'emergency' => '💥',
            default => '❗',
        };
        
        $formattedMessage = "<b>{$levelIcon} [{$level}]</b>\n";
        $formattedMessage .= "<pre>" . htmlspecialchars($message) . "</pre>\n";
        
        // Добавляем информацию об окружении
        $formattedMessage .= "\n<b>Environment:</b>\n";
        $formattedMessage .= "App: " . config('app.name', 'PhotoAuthApp') . "\n";
        $formattedMessage .= "Version: " . config('app.version', 'unknown') . "\n";
        $formattedMessage .= "Environment: " . app()->environment() . "\n";
        
        // Добавляем дополнительную информацию из контекста
        if (!empty($context)) {
            $formattedMessage .= "\n<b>Context:</b>\n";
            foreach ($context as $key => $value) {
                $formattedMessage .= "{$key}: " . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
            }
        }
        
        // Добавляем timestamp
        $formattedMessage .= "\n<b>Time:</b> " . now()->format('Y-m-d H:i:s T');
        
        return $formattedMessage;
    }
    
    /**
     * Проверяет, включена ли отправка в Telegram
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->botToken) && !empty($this->chatId);
    }
}
```

## 3. Интеграция в существующий код

### 3.1 Обновление LicenseService.php
В методе `validateLicense` добавим отправку ошибок:

```php
// В app/Services/LicenseService.php
public function validateLicense($licenseContent)
{
    try {
        // ... существующий код
        
        // Если возникает ошибка валидации
        if (!$license['valid']) {
            $telegram = new TelegramLogger();
            $telegram->send('Ошибка валидации лицензии', 'error', [
                'reason' => $license['reason'] ?? 'unknown',
                'license_content' => substr($licenseContent, 0, 100) . '...',
            ]);
        }
    } catch (\Exception $e) {
        $telegram = new TelegramLogger();
        $telegram->send('Ошибка при проверке лицензии', 'critical', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}
```

### 3.2 Обновление FtpAuthController.php
В методах работы с FTP добавим отправку ошибок:

```php
// В app/Http/Controllers/FtpAuthController.php
public function fetchPhotoFromFtp($url)
{
    try {
        // ... существующий код
        
        if (!$response->successful()) {
            $telegram = new TelegramLogger();
            $telegram->send('Ошибка HTTP запроса к FTP', 'warning', [
                'url' => $url,
                'status' => $response->status(),
            ]);
        }
    } catch (\Exception $e) {
        $telegram = new TelegramLogger();
        $telegram->send('Ошибка при получении фото из FTP', 'error', [
            'url' => $url,
            'exception' => $e->getMessage(),
        ]);
    }
}
```

### 3.3 Обновление AppSettingsFileStore.php
В методе `loadDecrypted` добавим отправку ошибок:

```php
// В app/Services/AppSettingsFileStore.php
public function loadDecrypted(): array
{
    $data = $this->loadRaw();

    foreach ($this->secretKeys() as $k) {
        if (!empty($data[$k])) {
            try {
                $data[$k] = Crypt::decryptString((string)$data[$k]);
            } catch (\Throwable $e) {
                // если в файле мусор — лучше не падать админке
                $data[$k] = '';
                
                // Отправляем предупреждение в Telegram
                $telegram = new TelegramLogger();
                $telegram->send('Ошибка расшифровки настроек', 'warning', [
                    'key' => $k,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    return $data;
}
```

### 3.4 Обновление ThumbnailController.php
В методах генерации миниатюр:

```php
// В app/Http/Controllers/ThumbnailController.php
public function generateThumbnail($path)
{
    try {
        // ... существующий код
        
        if (! $resp->ok()) {
            $telegram = new TelegramLogger();
            $telegram->send('Оригинал фото не найден', 'warning', [
                'path' => $path,
                'status' => $resp->status(),
            ]);
        }
    } catch (\Exception $e) {
        $telegram = new TelegramLogger();
        $telegram->send('Ошибка генерации миниатюры', 'error', [
            'path' => $path,
            'exception' => $e->getMessage(),
        ]);
    }
}
```

### 3.5 Обновление Console Command GenerateThumbnails.php
```php
// В app/Console/Commands/GenerateThumbnails.php
public function handle()
{
    try {
        // ... существующий код
        
        $allFiles = $ftpDisk->allFiles($rootDir);
    } catch (\Throwable $e) {
        $telegram = new TelegramLogger();
        $telegram->send('Ошибка получения списка файлов из фотохранилища', 'critical', [
            'error' => $e->getMessage(),
        ]);
        
        $this->warn('Не удалось получить список файлов из фотохранилища: ' . $e->getMessage());
    }
}
```

## 4. Создание Facade для удобного использования

### 4.1 Создание Telegram facade
```php
<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Telegram extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'telegram.logger';
    }
}
```

### 4.2 Регистрация в провайдере
В `app/Providers/AppServiceProvider.php` добавить:
```php
public function register()
{
    // ... существующий код
    
    $this->app->singleton('telegram.logger', function ($app) {
        return new \App\Services\TelegramLogger();
    });
}
```

### 4.3 Использование через Facade
```php
use App\Facades\Telegram;

// В коде
Telegram::send('Сообщение об ошибке', 'error', ['context' => 'data']);
```

## 5. Фильтрация и категоризация ошибок

### 5.1 Категории ошибок
- **Критические ошибки**: всегда отправляются
- **Ошибки**: отправляются при включенной функции
- **Предупреждения**: отправляются при включенной функции
- **Информационные**: отправляются при включенной функции

### 5.2 Примеры использования
```php
// Критическая ошибка - всегда отправляется
$telegram->send('База данных недоступна', 'emergency');

// Ошибка - отправляется при включенной функции
$telegram->send('Ошибка подключения к FTP', 'error');

// Предупреждение - отправляется при включенной функции
$telegram->send('Деградация сервиса', 'warning');

// Информационное сообщение - отправляется при включенной функции
$telegram->send('Система успешно запущена', 'info');
```

## 6. Тестирование и отладка

### 6.1 Тестирование отправки
Создать тестовый endpoint для проверки работы:
```php
// В routes/web.php или routes/api.php
Route::get('/test-telegram', function () {
    $telegram = new TelegramLogger();
    $result = $telegram->send('Тестовое сообщение из PhotoAuthApp', 'info');
    return response()->json(['sent' => $result]);
});
```

### 6.2 Логирование ошибок
Все ошибки отправки в Telegram также логгируются в стандартный логгер Laravel.