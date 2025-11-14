<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Политики модели → политика (если понадобятся).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Зарегистрировать политики и прочие возможности.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Пример Gate (если нужно):
        // Gate::define('view-admin', fn($user) => $user->role->NameRole === 'Администратор');
    }
}
