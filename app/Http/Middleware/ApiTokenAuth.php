<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $expected = env('DEVICE_PAIRS_API_TOKEN');

        if (!$expected) {
            return response()->json(['error' => 'API token not configured'], 500);
        }

        $provided = $request->header('X-Api-Token');

        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // (Опционально) ограничение по IP, если нужно:
        // DEVICE_PAIRS_API_IP_ALLOWLIST="127.0.0.1,192.168.1.10"
        $allow = env('DEVICE_PAIRS_API_IP_ALLOWLIST');
        if ($allow) {
            $allowed = array_filter(array_map('trim', explode(',', $allow)));
            $ip = $request->ip();
            if ($ip && !in_array($ip, $allowed, true)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        return $next($request);
    }
}
