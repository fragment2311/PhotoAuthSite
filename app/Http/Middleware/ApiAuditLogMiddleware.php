<?php

namespace App\Http\Middleware;

use App\Services\ErrorNotificationService;
use App\Support\ApiErrorFormatter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = (float) ($request->attributes->get('request_started_at') ?? microtime(true));

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $status = $response->getStatusCode();

        if (!$this->shouldWriteAudit($status)) {
            return $response;
        }

        $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');
        $requestId = ApiErrorFormatter::resolveRequestId($request);
        $clientIp = ApiErrorFormatter::resolveClientIp($request) ?? $request->ip();

        $clientId = trim((string) $request->header('X-Client-Id', (string) $request->query('client_id', '')));

        try {
            app(ErrorNotificationService::class)->log($level, 'API request processed', [
                'suppress_notifications' => true,
                'request_id' => $requestId,
                'request_method' => $request->method(),
                'request_path' => $request->path(),
                'request_url' => $request->fullUrl(),
                'request_ip' => $clientIp,
                'request_user_agent' => (string) $request->userAgent(),
                'status_code' => $status,
                'duration_ms' => $durationMs,
                'client_id' => $clientId !== '' ? $clientId : null,
            ]);
        } catch (\Throwable) {
            // never break API response because of audit logging
        }

        return $response;
    }

    private function shouldWriteAudit(int $status): bool
    {
        if ($status >= 400) {
            return true;
        }

        $sample = (float) config('services.error_notifications.api_audit_success_sample', 0.02);

        if ($sample <= 0) {
            return false;
        }

        if ($sample >= 1) {
            return true;
        }

        return mt_rand(1, 1000000) <= (int) round($sample * 1000000);
    }
}

