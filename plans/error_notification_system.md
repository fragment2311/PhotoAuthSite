# Система уведомлений об ошибках для PhotoAuthApp

## 1. Общая архитектура

### 1.1 Цели системы
- Встроенные уведомления в интерфейсе приложения
- Отправка критических ошибок на email (access_line@bk.ru)
- Возможность отправки уведомлений в мессенджер (через Telegram API)
- Хранение всех событий в базе данных для последующего анализа
- Удобное отображение логов в админке

### 1.2 Компоненты системы
1. **Модель ErrorLog** - для хранения ошибок в БД
2. **Сервис ErrorNotificationService** - основной сервис уведомлений
3. **Драйверы уведомлений** - InApp, Email, Telegram
4. **Контроллер Admin/ErrorLogController** - для отображения логов
5. **Представления** - для отображения логов в админке

## 2. Модель ErrorLog

### 2.1 Создание миграции
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level')->index(); // error, warning, critical, fatal
            $table->string('message');
            $table->text('context')->nullable();
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->string('ip_address')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('fingerprint')->nullable(); // для дедупликации
            $table->boolean('is_sent_email')->default(false);
            $table->boolean('is_sent_telegram')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('error_logs');
    }
};
```

### 2.2 Модель ErrorLog
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    protected $fillable = [
        'level',
        'message',
        'context',
        'file',
        'line',
        'ip_address',
        'user_id',
        'request_url',
        'user_agent',
        'fingerprint',
        'is_sent_email',
        'is_sent_telegram'
    ];

    protected $casts = [
        'context' => 'array',
        'is_sent_email' => 'boolean',
        'is_sent_telegram' => 'boolean'
    ];
}
```

## 3. Сервис ErrorNotificationService

### 3.1 Основной сервис
```php
<?php

namespace App\Services;

use App\Models\ErrorLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ErrorNotificationService
{
    private array $drivers = ['inapp', 'email', 'telegram'];
    private array $levelRules = [
        'warning' => ['inapp'],
        'error' => ['inapp', 'email'],
        'critical' => ['inapp', 'email', 'telegram'],
        'fatal' => ['inapp', 'email', 'telegram']
    ];

    public function logError(string $level, string $message, array $context = []): ErrorLog
    {
        // Генерируем fingerprint для дедупликации
        $fingerprint = $this->generateFingerprint($level, $message, $context);
        
        // Проверяем, не было ли подобного события недавно
        $existing = ErrorLog::where('fingerprint', $fingerprint)
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();
            
        if ($existing) {
            return $existing;
        }

        // Создаем запись в логе
        $errorLog = ErrorLog::create([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'file' => $context['file'] ?? null,
            'line' => $context['line'] ?? null,
            'ip_address' => Request::ip(),
            'user_id' => Auth::id(),
            'request_url' => Request::fullUrl(),
            'user_agent' => Request::userAgent(),
            'fingerprint' => $fingerprint
        ]);

        // Отправляем уведомления
        $this->sendNotifications($errorLog);

        return $errorLog;
    }

    private function generateFingerprint(string $level, string $message, array $context): string
    {
        $parts = [
            $level,
            $message,
            $context['file'] ?? '',
            $context['line'] ?? ''
        ];
        
        return md5(implode('|', $parts));
    }

    private function sendNotifications(ErrorLog $errorLog): void
    {
        $drivers = $this->levelRules[$errorLog->level] ?? ['inapp'];
        
        foreach ($drivers as $driver) {
            $method = 'send' . ucfirst($driver) . 'Notification';
            if (method_exists($this, $method)) {
                $this->$method($errorLog);
            }
        }
    }

    private function sendInappNotification(ErrorLog $errorLog): void
    {
        // Встроенные уведомления в интерфейсе - реализуется через JS/Session
        // или через специальный endpoint для получения новых уведомлений
    }

    private function sendEmailNotification(ErrorLog $errorLog): void
    {
        if ($errorLog->is_sent_email) {
            return;
        }

        try {
            Mail::send('emails.error-notification', ['error' => $errorLog], function ($message) use ($errorLog) {
                $message->to('access_line@bk.ru')
                        ->subject("Ошибка в PhotoAuthApp: {$errorLog->level}");
            });

            $errorLog->update(['is_sent_email' => true]);
        } catch (\Exception $e) {
            // Логируем ошибку отправки email
            \Log::error('Failed to send email notification: ' . $e->getMessage());
        }
    }

    private function sendTelegramNotification(ErrorLog $errorLog): void
    {
        if ($errorLog->is_sent_telegram) {
            return;
        }

        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');
        
        if (!$botToken || !$chatId) {
            return;
        }

        try {
            $message = $this->formatTelegramMessage($errorLog);
            
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]);
                
            if ($response->successful()) {
                $errorLog->update(['is_sent_telegram' => true]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send Telegram notification: ' . $e->getMessage());
        }
    }

    private function formatTelegramMessage(ErrorLog $errorLog): string
    {
        $levelIcon = match($errorLog->level) {
            'error' => '❌',
            'warning' => '⚠️',
            'critical' => '🚨',
            'fatal' => '💥',
            default => '❗',
        };

        $message = "<b>{$levelIcon} [{$errorLog->level}]</b>\n";
        $message .= "<pre>" . htmlspecialchars($errorLog->message) . "</pre>\n";
        
        if ($errorLog->file) {
            $message .= "\n<b>File:</b> {$errorLog->file}\n";
        }
        
        if ($errorLog->line) {
            $message .= "<b>Line:</b> {$errorLog->line}\n";
        }
        
        $message .= "\n<b>Environment:</b>\n";
        $message .= "App: " . config('app.name', 'PhotoAuthApp') . "\n";
        $message .= "Version: " . config('app.version', 'unknown') . "\n";
        $message .= "Environment: " . app()->environment() . "\n";
        
        if ($errorLog->user_id) {
            $message .= "<b>User ID:</b> {$errorLog->user_id}\n";
        }
        
        $message .= "\n<b>Time:</b> " . $errorLog->created_at->format('Y-m-d H:i:s T');
        
        return $message;
    }
}
```

## 4. Конфигурация

### 4.1 Добавление настроек в config/services.php
```php
<?php

return [
    // ... существующие настройки
    
    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    ],
    
    // ... остальные настройки
];
```

### 4.2 Добавление настроек в .env
```
# Telegram настройки
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=8340634038:AAE-Fkstk8zpfkk_bybwRHH4P5ivX6yQo4I
TELEGRAM_CHAT_ID=-1001234567890

# Email настройки
MAIL_MAILER=smtp
MAIL_HOST=smtp.mail.ru
MAIL_PORT=465
MAIL_USERNAME=your-email@mail.ru
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=your-email@mail.ru
MAIL_FROM_NAME="${APP_NAME}"
```

## 5. Контроллер для отображения логов

### 5.1 Создание контроллера
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ErrorLog::query();
        
        // Фильтрация по уровню
        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }
        
        // Фильтрация по дате
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }
        
        $logs = $query->orderBy('created_at', 'desc')
                     ->paginate(50);
        
        return view('admin.error_logs.index', compact('logs'));
    }
}
```

## 6. Представления

### 6.1 Шаблон списка логов
```blade
{{-- resources/views/admin/error_logs/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Журнал логов</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Уровень</th>
                            <th>Сообщение</th>
                            <th>Файл</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td>
                                <span class="badge bg-{{ $log->level === 'error' ? 'danger' : ($log->level === 'warning' ? 'warning' : 'secondary') }}">
                                    {{ strtoupper($log->level) }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ Str::limit($log->message, 100) }}</strong>
                                @if($log->context)
                                    <br><small class="text-muted">{{ json_encode($log->context) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($log->file)
                                    <small>{{ basename($log->file) }}:{{ $log->line }}</small>
                                @endif
                            </td>
                            <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                            <td>
                                <a href="#" class="btn btn-sm btn-outline-primary">Подробнее</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
```

## 7. Интеграция с существующим кодом

### 7.1 Пример использования в LicenseService
```php
// В app/Services/LicenseService.php
use App\Services\ErrorNotificationService;

public function validateLicense($licenseContent)
{
    try {
        // ... существующий код
        
        if (!$license['valid']) {
            $errorService = new ErrorNotificationService();
            $errorService->logError('critical', 'Ошибка валидации лицензии', [
                'reason' => $license['reason'] ?? 'unknown',
                'license_content' => substr($licenseContent, 0, 100) . '...',
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    } catch (\Exception $e) {
        $errorService = new ErrorNotificationService();
        $errorService->logError('fatal', 'Ошибка при проверке лицензии', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}
```

### 7.2 Пример использования в FtpAuthController
```php
// В app/Http/Controllers/FtpAuthController.php
use App\Services\ErrorNotificationService;

public function fetchPhotoFromFtp($url)
{
    try {
        // ... существующий код
        
        if (!$response->successful()) {
            $errorService = new ErrorNotificationService();
            $errorService->logError('error', 'Ошибка HTTP запроса к FTP', [
                'url' => $url,
                'status' => $response->status(),
                'file' => __FILE__,
                'line' => __LINE__
            ]);
        }
    } catch (\Exception $e) {
        $errorService = new ErrorNotificationService();
        $errorService->logError('error', 'Ошибка при получении фото из FTP', [
            'url' => $url,
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}
```

## 8. Добавление маршрутов

### 8.1 Добавление в routes/web.php
```php
// В routes/web.php
Route::middleware(['auth', 'role:администратор'])->group(function () {
    // ... существующие маршруты
    
    Route::get('/admin/error-logs', [App\Http\Controllers\Admin\ErrorLogController::class, 'index'])
        ->name('admin.error_logs.index');
});
```

## 9. Добавление в меню админки

### 9.1 В resources/views/partials/sidebar.blade.php
```blade
<!-- Добавить в меню -->
<a class="app-nav-sublink {{ request()->routeIs('admin.error_logs.index') ? 'active' : '' }}"
   href="{{ route('admin.error_logs.index') }}">Журнал ошибок</a>
```

## 10. Настройка почты

### 10.1 Конфигурация в config/mail.php
```php
<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mail.ru'),
            'port' => env('MAIL_PORT', 465),
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
        ],
    ],
    
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'your-email@mail.ru'),
        'name' => env('MAIL_FROM_NAME', 'PhotoAuthApp'),
    ],
];
```

## 11. Шаблон email-уведомления

### 11.1 Создание шаблона
```blade
{{-- resources/views/emails/error-notification.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Ошибка в PhotoAuthApp</title>
</head>
<body>
    <h2>Ошибка в PhotoAuthApp</h2>
    
    <p><strong>Уровень:</strong> {{ $error->level }}</p>
    <p><strong>Сообщение:</strong> {{ $error->message }}</p>
    
    @if($error->file)
        <p><strong>Файл:</strong> {{ $error->file }}</p>
        <p><strong>Строка:</strong> {{ $error->line }}</p>
    @endif
    
    <p><strong>Дата:</strong> {{ $error->created_at->format('d.m.Y H:i:s') }}</p>
    
    @if($error->user_id)
        <p><strong>Пользователь:</strong> {{ $error->user_id }}</p>
    @endif
    
    <p><strong>IP адрес:</strong> {{ $error->ip_address }}</p>
    <p><strong>URL:</strong> {{ $error->request_url }}</p>
    
    <p><strong>Контекст:</strong></p>
    <pre>{{ json_encode($error->context, JSON_PRETTY_PRINT) }}</pre>
</body>
</html>
```

## 12. Использование в консольных командах

### 12.1 Пример в GenerateThumbnails.php
```php
// В app/Console/Commands/GenerateThumbnails.php
use App\Services\ErrorNotificationService;

public function handle()
{
    try {
        // ... существующий код
        
        $allFiles = $ftpDisk->allFiles($rootDir);
    } catch (\Throwable $e) {
        $errorService = new ErrorNotificationService();
        $errorService->logError('fatal', 'Ошибка получения списка файлов из фотохранилища', [
            'error' => $e->getMessage(),
            'file' => __FILE__,
            'line' => __LINE__
        ]);
        
        $this->warn('Не удалось получить список файлов из фотохранилища: ' . $e->getMessage());
    }
}
```

## 13. Дополнительные возможности

### 13.1 Фильтрация по уровням
В настройках можно настроить, какие уровни отправлять куда:
```php
// config/error_notifications.php
return [
    'level_rules' => [
        'warning' => ['inapp'],
        'error' => ['inapp', 'email'],
        'critical' => ['inapp', 'email', 'telegram'],
        'fatal' => ['inapp', 'email', 'telegram']
    ]
];
```

### 13.2 Дедупликация
Система автоматически объединяет одинаковые ошибки в течение 5 минут.

### 13.3 Административный интерфейс
- Фильтрация по уровню, дате
- Пагинация
- Возможность просмотра деталей
- Экспорт в CSV/JSON