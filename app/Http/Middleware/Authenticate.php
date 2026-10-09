<?php

namespace App\Http\Middleware;

use App\Services\BootstrapConfigService;
use App\Support\MasterAuthUser;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $isMasterSession = (bool) $request->session()->get('master_auth', false) === true
            || $this->hasMasterGuardSession($request);

        if ($isMasterSession) {
            $masterLogin = app(BootstrapConfigService::class)->getMasterLogin();
            Auth::setUser(new MasterAuthUser($masterLogin));
            $request->session()->put('master_auth', true);

            return $next($request);
        }

        return parent::handle($request, $next, ...$guards);
    }

    private function hasMasterGuardSession(Request $request): bool
    {
        try {
            $guard = Auth::guard();
            if (!method_exists($guard, 'getName')) {
                return false;
            }

            $guardSessionKey = (string) $guard->getName();
            if ($guardSessionKey === '') {
                return false;
            }

            return (string) $request->session()->get($guardSessionKey, '') === 'master-auth-user';
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function redirectTo(Request $request): ?string
    {
        if (!$request->expectsJson()) {
            return route('login');
        }

        return null;
    }
}

