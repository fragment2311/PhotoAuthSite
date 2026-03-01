<?php

namespace App\Http\Middleware;

use App\Support\MasterAuthUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        if ((bool) $request->session()->get('master_auth', false) === true) {
            Auth::setUser(new MasterAuthUser());
            return redirect()->route('gallery', ['dir' => trim((string) config('app.gallery_root_dir', 'entrance_photos'), '/') ?: 'entrance_photos']);
        }

        $guards = $guards === [] ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect()->route('gallery', ['dir' => trim((string) config('app.gallery_root_dir', 'entrance_photos'), '/') ?: 'entrance_photos']);
            }
        }

        return $next($request);
    }
}

