<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header('X-Request-Id', '');
        $requestId = $this->sanitizeRequestId($incoming) ?? (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('request_started_at', microtime(true));
        $request->headers->set('X-Request-Id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
        ]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function sanitizeRequestId(string $requestId): ?string
    {
        $normalized = trim($requestId);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 128) {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9._\\-]+$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}

