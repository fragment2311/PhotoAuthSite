<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Alias для route middleware (то, что тебе нужно)
        $middleware->alias([
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
        ]);

        // Если нужно — сюда же можно добавить группы/глобальные middleware
        // $middleware->append(...);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Можно оставить пустым — важно, что блок существует,
        // чтобы ExceptionHandler был корректно зарегистрирован.
    })
    ->create();
