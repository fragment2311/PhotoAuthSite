<?php

namespace App\Http\Middleware;

use App\Services\ErrorNotificationService;
use App\Support\ApiErrorFormatter;
use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) env('API_TOKEN', '');

        if ($expected === '') {
            return $this->deny(
                $request,
                500,
                'api_token_not_configured',
                'API token not configured',
                'critical'
            );
        }

        $provided = $request->header('X-Api-Token');

        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->deny(
                $request,
                401,
                'unauthorized',
                'Unauthorized',
                'warning'
            );
        }

        // (Опционально) ограничение по IP, если нужно:
        // DEVICE_PAIRS_API_IP_ALLOWLIST="127.0.0.1,192.168.1.10"
        $allow = env('DEVICE_PAIRS_API_IP_ALLOWLIST');
        if ($allow) {
            $allowed = array_filter(array_map('trim', explode(',', $allow)));
            $ip = ApiErrorFormatter::resolveClientIp($request) ?? $request->ip();
            if ($ip && !in_array($ip, $allowed, true)) {
                return $this->deny(
                    $request,
                    403,
                    'forbidden',
                    'Forbidden',
                    'warning'
                );
            }
        }

        return $next($request);
    }

    private function deny(Request $request, int $status, string $code, string $message, string $level)
    {
        $clientIp = ApiErrorFormatter::resolveClientIp($request) ?? $request->ip();

        try {
            app(ErrorNotificationService::class)->log($level, 'API access denied', [
                'reason' => $code,
                'status' => $status,
                'request_id' => ApiErrorFormatter::resolveRequestId($request),
                'request_method' => $request->method(),
                'request_path' => $request->path(),
                'request_url' => $request->fullUrl(),
                'request_ip' => $clientIp,
                'request_user_agent' => (string) $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // never block API response due to logging failure
        }

        return ApiErrorFormatter::response($request, $status, $code, $message);
    }
}
