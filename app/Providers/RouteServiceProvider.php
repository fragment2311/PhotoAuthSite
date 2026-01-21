<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RoleMiddleware; // не забудьте импорт
use App\Http\Middleware\ApiTokenAuth;

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
    // уже есть:
    Route::aliasMiddleware('role', RoleMiddleware::class);

    // ДОБАВЬ ВОТ ЭТО:
    Route::aliasMiddleware('api.token', ApiTokenAuth::class);

    $this->routes(function () {
        Route::middleware('web')
             ->group(base_path('routes/web.php'));

        Route::prefix('api')
             ->middleware('api')
             ->group(base_path('routes/api.php'));
    });
}

    
}
