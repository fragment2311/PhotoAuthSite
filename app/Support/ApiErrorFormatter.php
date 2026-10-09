<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiErrorFormatter
{
    /**
     * @param  array<string, mixed>  $extraError
     */
    public static function response(
        Request $request,
        int $status,
        string $code,
        string $message,
        array $extraError = []
    ): JsonResponse {
        $requestId = self::resolveRequestId($request);

        $error = array_merge([
            'code' => $code,
            'message' => $message,
            'request_id' => $requestId,
            'timestamp' => now()->toIso8601String(),
        ], $extraError);

        $payload = [
            'success' => false,
            'error' => $error,
        ];

        $response = response()->json($payload, $status);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function resolveRequestId(Request $request): string
    {
        $fromAttr = (string) $request->attributes->get('request_id', '');
        if ($fromAttr !== '') {
            return $fromAttr;
        }

        $fromHeader = trim((string) $request->header('X-Request-Id', ''));
        if ($fromHeader !== '') {
            return $fromHeader;
        }

        return (string) Str::uuid();
    }

    public static function resolveClientIp(Request $request): ?string
    {
        $candidates = [];

        foreach ($request->getClientIps() as $ip) {
            $normalized = trim((string) $ip);
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
        }

        foreach (['X-Forwarded-For', 'CF-Connecting-IP', 'X-Real-IP', 'X-Client-IP'] as $header) {
            $raw = (string) $request->header($header, '');
            if ($raw === '') {
                continue;
            }

            foreach (explode(',', $raw) as $part) {
                $normalized = trim($part);
                if ($normalized !== '') {
                    $candidates[] = $normalized;
                }
            }
        }

        $remoteAddr = trim((string) $request->server('REMOTE_ADDR', ''));
        if ($remoteAddr !== '') {
            $candidates[] = $remoteAddr;
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }
}

