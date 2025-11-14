<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    public function handle($request, Closure $next, $role)
    {
        if (!Auth::check() || Auth::user()->role->NameRole !== $role) {
            abort(403, 'Доступ запрещён');
        }
        return $next($request);
    }
}
