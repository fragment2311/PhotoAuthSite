<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Слушатели событий приложения.
     *
     * @var array
     */
    protected $listen = [
        //
        // Пример:
        // \App\Events\SomeEvent::class => [
        //     \App\Listeners\SomeListener::class,
        // ],
    ];

    /**
     * Зарегистрировать события и слушатели.
     */
    public function boot(): void
    {
        parent::boot();

        //
    }
}
