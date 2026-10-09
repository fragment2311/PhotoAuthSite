<?php

namespace App\Services;

use App\Support\NewtonsoftPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class LicenseService
{
    private const CACHE_KEY = 'license.status.v2';

    public function getStatus(): array
    {
        $ttl = max(30, (int) config('license.cache_ttl_seconds', 120));

        return Cache::remember(self::CACHE_KEY, $ttl, function () {
            $status = $this->evaluate();
            $this->logLicenseState($status, 'evaluate');

            return $status;
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function isValid(): bool
    {
        $st = $this->getStatus();
        return (bool)($st['valid'] ?? false);
    }

    public function hasFeature(string $feature): bool
    {
        $st = $this->getStatus();
        if (!($st['valid'] ?? false)) return false;
        return (bool)($st['features'][$feature] ?? false);
    }

    /**
     * Логика доступа к "отчету" (таблица + скачивание).
     * Возвращает:
     * - allowed_view: можно ли показывать отчет
     * - allowed_download: можно ли скачивать
     * - banner: ['show'=>bool,'text'=>string,'type'=>'danger'|'warning'|'info']
     * - reason: string|null
     */
    public function reportAccess(): array
    {
        $st = $this->getStatus();
        $tz = (string) config('license.timezone', 'UTC');

        // Нет лицензии/невалидна подпись/не совпал client_id/и т.д.
        if (!($st['valid'] ?? false)) {
            return $this->finalizeReportAccess([
                'allowed_view' => false,
                'allowed_download' => false,
                'reason' => $st['reason'] ?? 'license_invalid',
                'banner' => [
                    'show' => true,
                    'type' => 'danger',
                    'text' => $this->humanBannerTextForInvalid($st),
                ],
            ]);
        }

        // Фича отчета выключена лицензией
        if (!(bool)($st['features']['report'] ?? false)) {
            return $this->finalizeReportAccess([
                'allowed_view' => false,
                'allowed_download' => false,
                'reason' => 'feature_report_disabled',
                'banner' => [
                    'show' => true,
                    'type' => 'danger',
                    'text' => 'Функция "Отчет" отключена лицензией.',
                ],
            ]);
        }

        // Даты (в статусе мы держим valid_until как строку YYYY-MM-DD)
        $validUntil = (string)($st['valid_until'] ?? '');
        if ($validUntil === '') {
            return $this->finalizeReportAccess([
                'allowed_view' => false,
                'allowed_download' => false,
                'reason' => 'valid_until_missing',
                'banner' => [
                    'show' => true,
                    'type' => 'danger',
                    'text' => 'Лицензия повреждена: отсутствует valid_until.',
                ],
            ]);
        }

        $graceDays = max(0, (int) config('license.grace_days', 7));
        $warnBeforeDays = max(0, (int) config('license.warn_before_days', 7));

        try {
            $expiresAt = CarbonImmutable::createFromFormat('Y-m-d', $validUntil, $tz)->endOfDay();
        } catch (\Throwable) {
            return $this->finalizeReportAccess([
                'allowed_view' => false,
                'allowed_download' => false,
                'reason' => 'valid_until_parse_failed',
                'banner' => [
                    'show' => true,
                    'type' => 'danger',
                    'text' => 'Лицензия повреждена: некорректная дата valid_until.',
                ],
            ]);
        }

        $now = CarbonImmutable::now($tz);
        $graceUntil = $expiresAt->addDays($graceDays);

        // 1) Активна
        if ($now->lessThanOrEqualTo($expiresAt)) {
            $secondsLeft = $now->diffInSeconds($expiresAt, false); // >=0

            // Показываем баннер только если осталось <= warnBeforeDays
            $showWarn = ($warnBeforeDays > 0) && ($secondsLeft <= ($warnBeforeDays * 86400));

            return $this->finalizeReportAccess([
                'allowed_view' => true,
                'allowed_download' => true,
                'reason' => null,
                'banner' => [
                    'show' => $showWarn,
                    'type' => $showWarn ? 'danger' : 'info',
                    'text' => $showWarn
                        ? ('Лицензия заканчивается через ' . $this->humanizeSeconds($secondsLeft) . '.')
                        : '',
                ],
            ]);
        }

        // 2) Grace период
        if ($now->greaterThan($expiresAt) && $now->lessThanOrEqualTo($graceUntil)) {
            $secondsGraceLeft = $now->diffInSeconds($graceUntil, false); // >=0

            return $this->finalizeReportAccess([
                'allowed_view' => true,
                'allowed_download' => true,
                'reason' => 'grace_period',
                'banner' => [
                    'show' => true,
                    'type' => 'danger',
                    'text' => 'Лицензия истекла, но действует grace-период. Осталось '
                        . $this->humanizeSeconds($secondsGraceLeft) . '.',
                ],
            ]);
        }

        // 3) Grace закончился — блокируем
        return $this->finalizeReportAccess([
            'allowed_view' => false,
            'allowed_download' => false,
            'reason' => 'license_expired_grace_over',
            'banner' => [
                'show' => true,
                'type' => 'danger',
                'text' => 'Лицензия истекла, grace-период закончился. Отчет недоступен.',
            ],
        ]);
    }

    // -------------------- INTERNAL --------------------

    private function evaluate(): array
    {
        $licensePath   = (string) config('license.license_path', storage_path('app/license/license.lic'));
        $publicKeyPath = (string) config('license.public_key_path', base_path('keys/public_key.pem'));
        $tz            = (string) config('license.timezone', 'UTC');

        $expectedClientId = config('license.client_id');
        $allowedFeatures  = (array) config('license.allowed_features', ['report', 'export', 'calc']);

        if (!is_file($licensePath)) {
            $this->log('warning', 'License file not found', ['path' => $licensePath]);
            return $this->invalid('license_file_missing');
        }
        if (!is_readable($licensePath)) {
            $this->log('error', 'License file not readable', ['path' => $licensePath]);
            return $this->invalid('license_file_not_readable');
        }
        if (!is_file($publicKeyPath) || !is_readable($publicKeyPath)) {
            $this->log('error', 'Public key not found or not readable', ['path' => $publicKeyPath]);
            return $this->invalid('public_key_missing');
        }

        $raw = file_get_contents($licensePath);
        if ($raw === false || trim($raw) === '') {
            $this->log('warning', 'License file empty', ['path' => $licensePath]);
            return $this->invalid('license_empty');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $this->log('warning', 'License JSON invalid', [
                'path' => $licensePath,
                'json_error' => json_last_error_msg(),
            ]);
            return $this->invalid('license_json_invalid');
        }

        $required = ['client_id', 'valid_until', 'features', 'issued_at', 'signature'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $decoded)) {
                $this->log('warning', 'License missing required field', ['field' => $key]);
                return $this->invalid('license_missing_field:' . $key);
            }
        }

        if (!is_string($decoded['client_id']) || trim($decoded['client_id']) === '') return $this->invalid('client_id_invalid');
        if (!is_string($decoded['valid_until']) || trim($decoded['valid_until']) === '') return $this->invalid('valid_until_invalid');
        if (!is_string($decoded['issued_at']) || trim($decoded['issued_at']) === '') return $this->invalid('issued_at_invalid');
        if (!is_array($decoded['features'])) return $this->invalid('features_invalid');
        if (!is_string($decoded['signature']) || trim($decoded['signature']) === '') return $this->invalid('signature_missing');

        $clientId   = trim((string) $decoded['client_id']);
        $validUntil = trim((string) $decoded['valid_until']);
        $issuedAt   = trim((string) $decoded['issued_at']);
        $featuresIn = $decoded['features'];

        if (is_string($expectedClientId) && trim($expectedClientId) !== '') {
            $expectedClientId = trim($expectedClientId);
            if (!hash_equals($expectedClientId, $clientId)) {
                $this->log('warning', 'client_id mismatch', ['expected' => $expectedClientId, 'got' => $clientId]);
                return $this->invalid('client_id_mismatch');
            }
        }

        // Дата валидности проверяется в reportAccess (с учетом grace и UI),
        // но базовый парсинг тут тоже полезен (чтобы валидность вообще не считалась true при мусоре).
        try {
            CarbonImmutable::createFromFormat('Y-m-d', $validUntil, $tz);
            CarbonImmutable::createFromFormat('Y-m-d', $issuedAt, $tz);
        } catch (\Throwable $e) {
            $this->log('warning', 'Date parse failed', ['error' => $e->getMessage()]);
            return $this->invalid('date_parse_failed');
        }

        $signature = base64_decode((string)$decoded['signature'], true);
        if ($signature === false) {
            $this->log('warning', 'Signature base64 decode failed');
            return $this->invalid('signature_base64_invalid');
        }

        // payload JSON строго как C# Newtonsoft (SnakeCase, Formatting.None, порядок полей, features отсортирован)
        try {
            $payloadJson = NewtonsoftPayload::build($clientId, $validUntil, $issuedAt, $featuresIn);
        }  catch (\Throwable $e) {
    $this->log('error', 'Payload build failed', [
        'error' => $e->getMessage(),
        'client_id' => $clientId ?? null,
        'valid_until' => $validUntil ?? null,
        'issued_at' => $issuedAt ?? null,
        'features_type' => gettype($featuresIn ?? null),
        'json_last_error' => json_last_error_msg(),
    ]);
    return $this->invalid('payload_build_failed');
}


        $publicKeyPem = file_get_contents($publicKeyPath);
        if ($publicKeyPem === false || trim($publicKeyPem) === '') {
            $this->log('error', 'Public key empty', ['path' => $publicKeyPath]);
            return $this->invalid('public_key_empty');
        }

        $pubKey = openssl_pkey_get_public($publicKeyPem);
        if ($pubKey === false) {
            $this->log('error', 'openssl_pkey_get_public failed', ['openssl_error' => $this->drainOpenSslErrors()]);
            return $this->invalid('public_key_invalid');
        }

        $ok = openssl_verify($payloadJson, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pubKey);

        if ($ok !== 1) {
            $this->log('warning', 'Signature verification failed', ['openssl_error' => $this->drainOpenSslErrors()]);
            return $this->invalid('signature_invalid');
        }

        // нормализация features по allow-list
        $featuresOut = [];
        foreach ($allowedFeatures as $f) {
            $featuresOut[$f] = (bool)($featuresIn[$f] ?? false);
        }

        return [
            'valid' => true,
            'reason' => null,
            'client_id' => $clientId,
            'valid_until' => $validUntil,
            'issued_at' => $issuedAt,
            'features' => $featuresOut,
        ];
    }

    private function invalid(string $reason, array $extra = []): array
    {
        $this->log('warning', 'Лицензия невалидна', array_merge(['причина' => $reason], $extra));

        return array_merge([
            'valid' => false,
            'reason' => $reason,
            'features' => [],
        ], $extra);
    }

    private function logLicenseState(array $status, string $source): void
    {
        $this->log('info', 'Состояние лицензии', [
            'источник' => $source,
            'валидна' => (bool)($status['valid'] ?? false),
            'причина' => $status['reason'] ?? null,
            'client_id' => $status['client_id'] ?? null,
            'действует_до' => $status['valid_until'] ?? null,
            'функции' => $status['features'] ?? [],
        ]);
    }

    private function finalizeReportAccess(array $access): array
    {
        $this->log('info', 'Доступ к отчёту по лицензии', [
            'просмотр_разрешен' => (bool)($access['allowed_view'] ?? false),
            'скачивание_разрешено' => (bool)($access['allowed_download'] ?? false),
            'причина' => $access['reason'] ?? null,
            'баннер_показывать' => (bool)($access['banner']['show'] ?? false),
            'тип_баннера' => $access['banner']['type'] ?? null,
        ]);

        return $access;
    }

    private function humanBannerTextForInvalid(array $st): string
    {
        $reason = (string)($st['reason'] ?? 'unknown');

        return match (true) {
            str_starts_with($reason, 'license_file_missing') => 'Лицензия не установлена. Функционал отчета заблокирован.',
            str_starts_with($reason, 'license_empty') => 'Файл лицензии пустой. Функционал отчета заблокирован.',
            str_starts_with($reason, 'license_json_invalid') => 'Файл лицензии поврежден (не JSON). Функционал отчета заблокирован.',
            str_starts_with($reason, 'signature_invalid') => 'Подпись лицензии неверна. Функционал отчета заблокирован.',
            str_starts_with($reason, 'client_id_mismatch') => 'Лицензия не подходит для этого клиента (client_id). Функционал отчета заблокирован.',
            default => 'Лицензия отсутствует или невалидна. Функционал отчета заблокирован.',
        };
    }

    private function humanizeSeconds(int $seconds): string
    {
        if ($seconds < 0) $seconds = 0;

        $days = intdiv($seconds, 86400);
        $seconds -= $days * 86400;

        $hours = intdiv($seconds, 3600);
        $seconds -= $hours * 3600;

        $mins = intdiv($seconds, 60);

        $parts = [];
        if ($days > 0) $parts[] = $days . ' дн.';
        if ($hours > 0 || $days > 0) $parts[] = $hours . ' ч.';
        $parts[] = $mins . ' мин.';

        return implode(' ', $parts);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $safeMessage = $this->buildSafeLicenseMessage($message, $context);

        // Для лицензии не передаём JSON/контекст в общий журнал и файл лога,
        // чтобы не раскрывать конфигурационные детали.
        app(ErrorNotificationService::class)->log($level, $safeMessage, []);

        $this->appendToUnifiedLog($level, $safeMessage, []);
    }

    private function buildSafeLicenseMessage(string $message, array $context): string
    {
        $reason = $context['причина'] ?? $context['reason'] ?? null;

        if (!is_string($reason) || trim($reason) === '') {
            return $message;
        }

        return $message . ' (код: ' . trim($reason) . ')';
    }

    private function appendToUnifiedLog(string $level, string $message, array $context = []): void
    {
        $path = (string) config('license.log_path', storage_path('logs/license.log'));
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = $this->formatUnifiedLogLine($level, $message, $context) . PHP_EOL;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        $this->pruneUnifiedLogSometimes($path);
    }

    private function formatUnifiedLogLine(string $level, string $message, array $context = []): string
    {
        $tz = (string) config('license.timezone', 'UTC');
        $stamp = CarbonImmutable::now($tz)->format('Y-m-d H:i:s');

        $suffix = '';
        if ($context !== []) {
            $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $json = '{"context_encode_error":"' . json_last_error_msg() . '"}';
            }
            $suffix = ' ' . $json;
        }

        return sprintf('[%s] [%s] %s%s', $stamp, strtoupper($level), $message, $suffix);
    }

    private function pruneUnifiedLogSometimes(string $path): void
    {
        $chance = (int) config('license.log_prune_chance_percent', 5);
        $chance = max(0, min(100, $chance));
        if ($chance === 0) {
            return;
        }

        try {
            if (random_int(1, 100) > $chance) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        clearstatcache(true, $path);
        if (!is_file($path)) {
            return;
        }

        $size = @filesize($path);
        if (!is_int($size) || $size <= 0) {
            return;
        }

        $maxSize = max(1024, (int) config('license.log_max_size_bytes', 5 * 1024 * 1024));
        $keepSize = max(512, (int) config('license.log_keep_bytes', 1024 * 1024));
        if ($keepSize > $maxSize) {
            $keepSize = $maxSize;
        }

        if ($size <= $maxSize) {
            return;
        }

        $fp = @fopen($path, 'rb');
        if ($fp === false) {
            return;
        }

        $offset = max(0, $size - $keepSize);
        @fseek($fp, $offset);
        $tail = (string) stream_get_contents($fp);
        @fclose($fp);

        if ($offset > 0) {
            $firstNewLinePos = strpos($tail, "\n");
            if ($firstNewLinePos !== false) {
                $tail = (string) substr($tail, $firstNewLinePos + 1);
            }
        }

        @file_put_contents($path, $tail, LOCK_EX);
    }

    private function drainOpenSslErrors(): array
    {
        $errs = [];
        while ($e = openssl_error_string()) {
            $errs[] = $e;
        }
        return $errs;
    }
}
