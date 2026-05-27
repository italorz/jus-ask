<?php

namespace App\Providers;

use App\Models\User;
use App\Services\TenantManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantManager::class);
    }

    public function boot(): void
    {
        Gate::define('super-admin', fn (User $user) => $user->isSuperAdmin());
    }
}
