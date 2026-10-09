# Финальный план реализации системы уведомлений

## 1. Общие принципы

### 1.1 Без настроек в интерфейсе
- Все настройки будут прописаны в коде/конфигах
- Доступ к просмотру логов ограничен только администраторами
- Никаких настроек в интерфейсе для управления уведомлениями
- Система работает как "черный ящик"

### 1.2 Основные требования
- Все логи хранятся в БД
- Отображение логов в админке с фильтрами по типу, дате и времени
- Поиск по логам
- Доступ только для администраторов
- Автоматическая отправка уведомлений в Telegram и Email

## 2. Архитектура системы

### 2.1 Модель ErrorLog
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

### 2.2 Сервис ErrorNotificationService
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
        // Встроенные уведомления в интерфейсе
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

## 3. Миграция для таблицы логов

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
            $table->string('fingerprint')->nullable();
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

## 4. Интеграция в существующий код

### 4.1 Пример использования в LicenseService
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

### 4.2 Пример использования в FtpAuthController
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

## 5. Административный интерфейс

### 5.1 Контроллер для отображения логов
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
        
        // Фильтрация по поиску
        if ($request->filled('search')) {
            $query->where('message', 'LIKE', '%' . $request->search . '%');
        }
        
        $logs = $query->orderBy('created_at', 'desc')
                     ->paginate(50);
        
        return view('admin.error_logs.index', compact('logs'));
    }
}
```

### 5.2 Шаблон отображения логов
```blade
{{-- resources/views/admin/error_logs/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Журнал ошибок</h1>
    </div>

    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <select name="level" class="form-control">
                        <option value="">Все уровни</option>
                        <option value="warning" {{ request('level') == 'warning' ? 'selected' : '' }}>Warning</option>
                        <option value="error" {{ request('level') == 'error' ? 'selected' : '' }}>Error</option>
                        <option value="critical" {{ request('level') == 'critical' ? 'selected' : '' }}>Critical</option>
                        <option value="fatal" {{ request('level') == 'fatal' ? 'selected' : '' }}>Fatal</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Поиск..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">Фильтровать</button>
                    <a href="{{ route('admin.error_logs.index') }}" class="btn btn-secondary">Сброс</a>
                </div>
            </form>
        </div>
        
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

## 6. Конфигурация

### 6.1 Настройки в config/services.php
```php
<?php

return [
    // ... существующие настройки
    
    'telegram' => [
        'enabled' => true,
        'bot_token' => '8340634038:AAE-Fkstk8zpfkk_bybwRHH4P5ivX6yQo4I',
        'chat_id' => '-1001234567890',
    ],
    
    // ... остальные настройки
];
```

### 6.2 Настройки в .env
```
# Telegram настройки
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=8340634038:AAE-Fkstk8zpfkk_bybwRHH4P5ivX6yQo4I
TELEGRAM_CHAT_ID=-1001234567890
```

## 7. Маршруты
```php
// В routes/web.php
Route::middleware(['auth', 'role:администратор'])->group(function () {
    // ... существующие маршруты
    
    Route::get('/admin/error-logs', [App\Http\Controllers\Admin\ErrorLogController::class, 'index'])
        ->name('admin.error_logs.index');
});
```

## 8. Доступность
- Доступ к журналу ошибок только для пользователей с ролью "администратор"
- Никаких настроек в интерфейсе для управления уведомлениями
- Все настройки прописаны в коде/конфигах
- Система работает как "черный ящик" - логи собираются автоматически и отображаются

## 9. Особенности реализации
- Автоматическая дедупликация одинаковых ошибок в течение 5 минут
- Поддержка всех уровней ошибок (warning, error, critical, fatal)
- Полная интеграция с существующим кодом
- Минимальная нагрузка на систему
- Безопасное хранение данных в БД