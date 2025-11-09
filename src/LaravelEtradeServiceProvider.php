<?php

namespace KevinRider\LaravelEtrade;

use Illuminate\Support\ServiceProvider;

class LaravelEtradeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EtradeApiClient::class, function ($app) {
            return new EtradeApiClient(
                config('laravel-etrade.app_key'),
                config('laravel-etrade.app_secret'),
                config('laravel-etrade.production'),
            );
        });
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-etrade.php',
            'laravel-etrade'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-etrade.php' => config_path('laravel-etrade.php'),
        ]);
    }
}
