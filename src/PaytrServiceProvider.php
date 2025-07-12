<?php
namespace Paytr;

use Illuminate\Support\ServiceProvider;

use Illuminate\Routing\Router;
use Paytr\Http\Middleware\VerifyPaytrSignature;

/**
 * PayTR Laravel Client Service Provider
 * Tüm servislerin Laravel'e kaydı ve publish işlemleri burada yapılır.
 */
class PaytrServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router)
    {
        // Sadece config publish
        $this->publishes([
            __DIR__.'/../config/paytr.php' => config_path('paytr.php'),
        ], 'paytr-config');

        // Register middleware alias
        $router->aliasMiddleware('paytr.signature', VerifyPaytrSignature::class);
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
