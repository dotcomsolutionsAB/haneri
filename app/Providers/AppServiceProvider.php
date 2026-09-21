<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Utils\MobileHelper;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(DelhiveryService::class, function ($app) {
            return new DelhiveryService();
        });
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

        // OTP endpoints are keyed by mobile number (not IP: requests reach us through a proxy),
        // which stops SMS flooding of a single number and brute-forcing its 6-digit OTP.
        RateLimiter::for('otp-request', fn (Request $request) => Limit::perMinute(3)
            ->by('otp-request|' . MobileHelper::normalize((string) $request->input('mobile'))));
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(8)
            ->by('otp-verify|' . MobileHelper::normalize((string) $request->input('mobile'))));
    }
}
