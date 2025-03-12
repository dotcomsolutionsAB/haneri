<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        $this->app['router']->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);

        // Register CorsMiddleware
        $this->app['router']->aliasMiddleware('cors', \App\Http\Middleware\CorsMiddleware::class);
    }
}
