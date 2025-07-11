<?php
namespace Paytr;

use Illuminate\Support\ServiceProvider;

/**
 * PayTR Laravel Client Service Provider
 * Tüm servislerin Laravel'e kaydı ve publish işlemleri burada yapılır.
 */
class PaytrServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // Sadece config publish
        $this->publishes([
            __DIR__.'/../config/paytr.php' => config_path('paytr.php'),
        ], 'paytr-config');
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/paytr.php', 'paytr'
        );

        $this->app->singleton('paytr', function ($app) {
            return new PaytrManager($app);
        });
    }
}
