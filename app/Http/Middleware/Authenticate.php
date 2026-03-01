<?php

namespace App\Http\Middleware;

use App\Support\MasterAuthUser;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        if ((bool) $request->session()->get('master_auth', false) === true) {
            Auth::setUser(new MasterAuthUser());
            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    protected function redirectTo(Request $request): ?string
    {
        if (!$request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}

