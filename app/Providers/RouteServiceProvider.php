<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RoleMiddleware; // не забудьте импорт

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Куда отправлять залогиненного (используется guest-middleware).
     */
    public const HOME = '/';

    /**
     * Определите маршруты для приложения.
     */
    public function boot(): void
    {
        // Регистрируем alias 'role' для вашего middleware
        Route::aliasMiddleware('role', RoleMiddleware::class);

        $this->routes(function () {
            // Web-маршруты
            Route::middleware('web')
                 ->group(base_path('routes/web.php'));

            // API-маршруты, если нужны
            // Route::prefix('api')
            //      ->middleware('api')
            //      ->group(base_path('routes/api.php'));
        });
    }
}
