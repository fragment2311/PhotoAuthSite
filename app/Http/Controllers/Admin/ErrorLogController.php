<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Services\ErrorNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemLog::query();

        $sort = trim((string) $request->input('sort', 'date'));
        $dir = mb_strtolower(trim((string) $request->input('dir', 'desc')));

        $sortMap = [
            'id' => 'Id',
            'date' => 'EventDate',
            'level' => 'LogType',
            'message' => 'Message',
            'ip' => 'IPAddress',
        ];

        if (!array_key_exists($sort, $sortMap)) {
            $sort = 'date';
        }

        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $level = trim((string) $request->input('level', ''));
        if ($level !== '') {
            $query->where('LogType', $level);
        }

        $dateFrom = trim((string) $request->input('date_from', ''));
        if ($dateFrom !== '') {
            $query->where('EventDate', '>=', $dateFrom);
        }

        $dateTo = trim((string) $request->input('date_to', ''));
        if ($dateTo !== '') {
            $query->where('EventDate', '<=', $dateTo);
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(static function ($q) use ($search): void {
                $q->where('Message', 'like', '%' . $search . '%');
            });
        }

        $logs = $query
            ->orderBy($sortMap[$sort], $dir)
            ->orderByDesc('Id')
            ->paginate(50)
            ->appends($request->except('page'));

        return view('admin.logs.index', [
            'logs' => $logs,
            'levels' => ['warning', 'error', 'critical', 'fatal', 'alert', 'emergency', 'notice', 'info', 'debug'],
            'currentSort' => $sort,
            'currentDir' => $dir,
        ]);
    }

    public function triggerTestError(Request $request, ErrorNotificationService $errorService)
    {
        $headers = collect($request->headers->all())
            ->except(['cookie', 'authorization'])
            ->map(static function ($value) {
                if (is_array($value)) {
                    return implode('; ', array_map(static fn ($v): string => (string) $v, $value));
                }

                return (string) $value;
            })
            ->toArray();

        $context = [
            'test_mode' => true,
            'description' => 'Временный ручной запуск проверки отправки отчёта об ошибке',
            'requested_at' => now()->toDateTimeString(),
            'requested_url' => $request->fullUrl(),
            'request_method' => $request->method(),
            'request_ip' => $request->ip(),
            'user_id' => Auth::id(),
            'app_env' => app()->environment(),
            'php_version' => PHP_VERSION,
            'server_software' => (string) $request->server('SERVER_SOFTWARE', ''),
            'headers' => $headers,
            'query_params' => $request->query(),
            'long_diagnostic_payload' => str_repeat('ДИАГНОСТИКА_ОТПРАВКИ_ОШИБКИ_', 600),
        ];

        try {
            throw new \RuntimeException('Тестовое исключение для проверки отправки отчёта.');
        } catch (\Throwable $e) {
            $context['exception'] = $e;
            $context['exception_message'] = $e->getMessage();
            $context['exception_file'] = $e->getFile();
            $context['exception_line'] = $e->getLine();

            $errorService->log('critical', '[TEST] Проверка отправки отчёта об ошибке', $context);
        }

        $mailer = (string) config('mail.default', 'log');
        $mailHost = (string) config('mail.mailers.smtp.host', '');
        $telegramEnabled = (bool) config('services.telegram.enabled', false);
        $telegramToken = trim((string) config('services.telegram.bot_token', ''));
        $telegramChatId = trim((string) config('services.telegram.chat_id', ''));

        $mailConfigured = $mailer !== 'log' && $mailer !== 'array' && $mailHost !== '' && $mailHost !== '127.0.0.1';
        $telegramConfigured = $telegramEnabled && $telegramToken !== '' && $telegramChatId !== '';

        if (!$mailConfigured || !$telegramConfigured) {
            $parts = [];
            if (!$mailConfigured) {
                $parts[] = 'Email не настроен (проверьте MAIL_* и mail.default).';
            }
            if (!$telegramConfigured) {
                $parts[] = 'Telegram не настроен (проверьте TELEGRAM_ENABLED, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID).';
            }

            return back()->with('err', 'Тестовый лог создан, но отправка каналов неполная: ' . implode(' ', $parts));
        }

        return back()->with('ok', 'Тестовая ошибка отправлена. Проверьте email/Telegram и запись в журнале логов.');
    }
}

