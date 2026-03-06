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
            return redirect()->route('gallery', ['dir' => $this->galleryRootDir()]);
        }

        $guards = $guards === [] ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect()->route('gallery', ['dir' => $this->galleryRootDir()]);
            }
        }

        return $next($request);
    }

    private function galleryRootDir(): string
    {
        $current = trim((string) config('app.gallery_root_dir', ''), '/');
        if ($current !== '') {
            return $current;
        }

        return trim((string) config('app.gallery_root_dir_default', ''), '/');
    }
}

