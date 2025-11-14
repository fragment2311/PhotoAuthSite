<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role->NameRole, $roles, true)) {
            abort(403, 'Доступ запрещён.');
        }
        return $next($request);
    }
}
