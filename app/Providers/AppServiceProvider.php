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
    // public function boot(): void
    // {
    //     //
    //     $this->app['router']->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
    // }
    public function boot()
    {
        // Force HTTPS for URL generation in non-local environments
        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }
    }
}
