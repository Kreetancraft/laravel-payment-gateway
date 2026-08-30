<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kreetancraft\PaymentGateway\Console\InstallCommand;
use Kreetancraft\PaymentGateway\Console\SyncGatewaysCommand;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Facades\PaymentGateway as PaymentGatewayFacade;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/payment-gateway.php',
            'payment-gateway'
        );

        $this->app->singleton(GatewayResolver::class, function ($app): PaymentGatewayManager {
            return new PaymentGatewayManager($app);
        });

        $this->app->singleton('payment-gateway', function ($app): PaymentGatewayManager {
            return $app->make(GatewayResolver::class);
        });

        $this->app->alias(GatewayResolver::class, PaymentGatewayManager::class);
        $this->app->alias('payment-gateway', PaymentGatewayManager::class);

        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            /** @var PaymentGatewayManager $manager */
            $manager = $app->make(GatewayResolver::class);

            return $manager->driver();
        });

        $this->app->alias(PaymentGatewayFacade::class, 'payment-gateway.facade');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'payment-gateway');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/payment-gateway.php' => config_path('payment-gateway.php'),
            ], 'payment-gateway-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/payment-gateway'),
            ], 'payment-gateway-views');

            $this->publishesMigrations([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'payment-gateway-migrations');

            $this->commands([
                InstallCommand::class,
                SyncGatewaysCommand::class,
            ]);
        }

        $loader = AliasLoader::getInstance();
        $loader->alias('PaymentGateway', PaymentGatewayFacade::class);

        Blade::componentNamespace('Kreetancraft\PaymentGateway\Livewire', 'payment-gateway');
    }

    public function provides(): array
    {
        return [
            GatewayResolver::class,
            PaymentGatewayManager::class,
            PaymentGateway::class,
            'payment-gateway',
        ];
    }
}