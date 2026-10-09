<?php

namespace App\Services;

use App\Jobs\SendErrorEmailJob;
use App\Models\SystemLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;

class ErrorNotificationService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(string $level, string $message, array $context = []): ?SystemLog
    {
        $normalizedLevel = $this->normalizeLevel($level);
        $preparedContext = $this->normalizeContext($context);
        $stackTrace = $this->buildStackTrace($normalizedLevel, $preparedContext);
        $suppressNotifications = (bool) ($preparedContext['suppress_notifications'] ?? false);
        $safeMessage = mb_substr(trim($message), 0, 1000);

        if ($safeMessage === '') {
            $safeMessage = 'Неизвестная ошибка';
        }

        $logEntry = null;

        try {
            $logEntry = SystemLog::query()->create([
                'Message' => $safeMessage,
                'EventDate' => now(),
                'LogType' => $normalizedLevel,
                'IPAddress' => $this->resolveIpAddress(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write error entry into Logs table', [
                'error' => $e->getMessage(),
                'level' => $normalizedLevel,
                'message' => $safeMessage,
            ]);
        }

        if (!$suppressNotifications) {
            $this->dispatchEmailIfNeeded($normalizedLevel, $safeMessage, $preparedContext, $stackTrace, $logEntry);
            $this->sendTelegramIfNeeded($normalizedLevel, $safeMessage, $preparedContext, $logEntry);
        }

        return $logEntry;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchEmailIfNeeded(
        string $level,
        string $message,
        array $context,
        string $stackTrace,
        ?SystemLog $logEntry
    ): void {
        if (!$this->shouldSendEmailNotification($level, $message, $context)) {
            return;
        }

        $job = new SendErrorEmailJob(
            $level,
            $message,
            $context,
            $stackTrace,
            $logEntry?->Id !== null ? (int) $logEntry->Id : null
        );

        $connection = trim((string) config('services.error_notifications.queue_connection', (string) config('queue.default', 'sync')));
        $queue = trim((string) config('services.error_notifications.queue_name', 'error-notifications'));

        if ($connection !== '') {
            $job->onConnection($connection);
        }

        if ($queue !== '') {
            $job->onQueue($queue);
        }

        try {
            dispatch($job);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch error email job, fallback to sync send', [
                'error' => $e->getMessage(),
                'level' => $level,
            ]);

            $this->sendEmailNow($level, $message, $context, $stackTrace, $logEntry?->Id !== null ? (int) $logEntry->Id : null);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function sendEmailNow(
        string $level,
        string $message,
        array $context,
        string $stackTrace,
        ?int $logId = null
    ): void {
        if (!in_array($level, ['error', 'critical', 'fatal', 'emergency', 'alert'], true)) {
            return;
        }

        $to = trim((string) config('services.error_notifications.email_to', 'access_line@bk.ru'));
        if ($to === '') {
            return;
        }

        $subject = sprintf('[%s] [%s] Ошибка в приложении', (string) config('app.name', 'PhotoAuthApp'), strtoupper($level));

        $contextJson = '';
        if ($context !== []) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $contextJson = $json === false ? 'Не удалось сериализовать контекст' : $json;
        }

        $detailsChunks = [];
        if ($stackTrace !== '') {
            $detailsChunks[] = "Технические детали:\n" . $stackTrace;
        }
        if ($contextJson !== '') {
            $detailsChunks[] = "Контекст:\n" . $contextJson;
        }

        $detailsPayload = trim(implode("\n\n", $detailsChunks));
        $attachDetailsAsFile = mb_strlen($detailsPayload) > 7000;

        $lines = [
            'Приложение: ' . (string) config('app.name', 'PhotoAuthApp'),
            'Окружение: ' . app()->environment(),
            'Уровень: ' . strtoupper($level),
            'Время: ' . Carbon::now()->format('Y-m-d H:i:s'),
            'IP: ' . (($context['request_ip'] ?? $context['ip'] ?? $this->resolveIpAddress()) ?: '-'),
            'ID записи Logs: ' . ($logId ?? $context['log_id'] ?? '-'),
            '',
            'Сообщение:',
            $message,
        ];

        if ($attachDetailsAsFile && $detailsPayload !== '') {
            $lines[] = '';
            $lines[] = 'Технические детали слишком объёмные — приложены файлом error-report-details.txt';
        } elseif ($detailsPayload !== '') {
            $lines[] = '';
            $lines[] = $detailsPayload;
        }

        try {
            Mail::raw(implode(PHP_EOL, $lines), function ($mail) use ($to, $subject, $attachDetailsAsFile, $detailsPayload): void {
                $mail->to($to)->subject($subject);

                if ($attachDetailsAsFile && $detailsPayload !== '') {
                    $mail->attachData($detailsPayload, 'error-report-details.txt', [
                        'mime' => 'text/plain; charset=UTF-8',
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send error notification email', [
                'error' => $e->getMessage(),
                'level' => $level,
                'to' => $to,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function shouldSendEmailNotification(string $level, string $message, array $context): bool
    {
        if (!in_array($level, ['error', 'critical', 'fatal', 'emergency', 'alert'], true)) {
            return false;
        }

        $to = trim((string) config('services.error_notifications.email_to', 'access_line@bk.ru'));
        if ($to === '') {
            return false;
        }

        $dedupeSeconds = (int) config('services.error_notifications.email_dedupe_seconds', 180);
        $rateLimitPerMinute = (int) config('services.error_notifications.email_rate_limit_per_minute', 30);
        $fingerprint = $this->buildNotificationFingerprint($level, $message, $context);

        if ($dedupeSeconds > 0) {
            $dedupeKey = 'error_notifications:email:dedupe:' . $fingerprint;
            $dedupeOk = Cache::add($dedupeKey, 1, now()->addSeconds($dedupeSeconds));

            if (!$dedupeOk) {
                return false;
            }
        }

        if ($rateLimitPerMinute > 0) {
            $bucket = now()->format('YmdHi');
            $rateKey = 'error_notifications:email:rate:' . $bucket;
            $current = Cache::get($rateKey);

            if ($current === null) {
                $count = 1;
                Cache::put($rateKey, 1, now()->addMinutes(2));
            } else {
                $count = (int) Cache::increment($rateKey);
            }

            if ($count > $rateLimitPerMinute) {
                Log::warning('Email notifications rate limit exceeded', [
                    'bucket' => $bucket,
                    'limit' => $rateLimitPerMinute,
                    'fingerprint' => $fingerprint,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildNotificationFingerprint(string $level, string $message, array $context): string
    {
        $parts = [
            mb_strtolower(trim($level)),
            trim($message),
            (string) ($context['request_path'] ?? ''),
            (string) ($context['request_method'] ?? ''),
            (string) ($context['exception_class'] ?? ''),
        ];

        return sha1(implode('|', $parts));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sendTelegramIfNeeded(string $level, string $message, array $context, ?SystemLog $logEntry): void
    {
        $enabled = (bool) config('services.telegram.enabled', false);
        if (!$enabled) {
            return;
        }

        $telegram = app(TelegramLogger::class);

        $telegramContext = array_merge($context, [
            'log_id' => $logEntry?->Id,
            'ip' => $this->resolveIpAddress(),
        ]);

        $telegram->send($message, $level, $telegramContext);
    }

    private function resolveIpAddress(): ?string
    {
        try {
            $candidates = [];

            foreach (Request::getClientIps() as $ip) {
                $candidates[] = trim((string) $ip);
            }

            foreach (['X-Forwarded-For', 'CF-Connecting-IP', 'X-Real-IP', 'X-Client-IP'] as $header) {
                $raw = (string) Request::header($header, '');
                if ($raw === '') {
                    continue;
                }

                foreach (explode(',', $raw) as $part) {
                    $candidates[] = trim($part);
                }
            }

            $serverRemoteAddr = (string) Request::server('REMOTE_ADDR', '');
            if ($serverRemoteAddr !== '') {
                $candidates[] = trim($serverRemoteAddr);
            }

            // Сначала ищем «публичный» IP клиента.
            foreach ($candidates as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }

            // Если публичного нет, берём любой валидный.
            foreach ($candidates as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeLevel(string $level): string
    {
        $normalized = mb_strtolower(trim($level));

        return match ($normalized) {
            'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'fatal' => $normalized,
            default => 'error',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $normalized[$key] = [
                    'message' => $value->getMessage(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => $value->getTraceAsString(),
                ];
                continue;
            }

            if ($value instanceof \DateTimeInterface) {
                $normalized[$key] = $value->format(DATE_ATOM);
                continue;
            }

            if (is_object($value)) {
                $normalized[$key] = method_exists($value, '__toString')
                    ? (string) $value
                    : get_class($value);
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildStackTrace(string $level, array $context): string
    {
        if (isset($context['exception']) && is_array($context['exception']) && isset($context['exception']['trace'])) {
            return (string) $context['exception']['trace'];
        }

        if (isset($context['trace']) && is_string($context['trace']) && trim($context['trace']) !== '') {
            return $context['trace'];
        }

        if ($context === []) {
            return '';
        }

        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        if (in_array($level, ['info', 'notice', 'debug'], true)) {
            return $json === false ? '' : $json;
        }

        return $json === false ? '' : $json;
    }
}

