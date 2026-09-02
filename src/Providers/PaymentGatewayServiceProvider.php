<?php

namespace Kreetancraft\PaymentGateway\Providers;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kreetancraft\PaymentGateway\Console\InstallCommand;
use Kreetancraft\PaymentGateway\Console\SyncGatewaysCommand;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use Kreetancraft\PaymentGateway\Contracts\PaymentGateway;
use Kreetancraft\PaymentGateway\Facades\PaymentGateway as PaymentGatewayFacade;
use Kreetancraft\PaymentGateway\Livewire\Checkout;
use Kreetancraft\PaymentGateway\Livewire\CreateCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditCoupon;
use Kreetancraft\PaymentGateway\Livewire\EditGateway;
use Kreetancraft\PaymentGateway\Livewire\ManageCoupons;
use Kreetancraft\PaymentGateway\Livewire\ManageGateways;
use Kreetancraft\PaymentGateway\Livewire\ManageTransactions;
use Kreetancraft\PaymentGateway\Livewire\ShowCoupon;
use Kreetancraft\PaymentGateway\Models\Coupon;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Kreetancraft\PaymentGateway\Models\Payment;
use Kreetancraft\PaymentGateway\PaymentGatewayManager;
use Kreetancraft\PaymentGateway\Policies\CouponPolicy;
use Kreetancraft\PaymentGateway\Policies\GatewayPolicy;
use Kreetancraft\PaymentGateway\Policies\PaymentPolicy;
use Livewire\Livewire;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        Payment::class => PaymentPolicy::class,
        Gateway::class => GatewayPolicy::class,
        Coupon::class => CouponPolicy::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/payment-gateway.php',
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

        $this->registerNavigation();
    }

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerLivewire();

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                SyncGatewaysCommand::class,
            ]);
        }

        $loader = AliasLoader::getInstance();
        $loader->alias('PaymentGateway', PaymentGatewayFacade::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/payment-gateway.php' => config_path('payment-gateway.php'),
        ], 'payment-gateway-config');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'payment-gateway');

        Blade::anonymousComponentPath(__DIR__.'/../../resources/views/components', 'payment-gateway');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/payment-gateway'),
        ], 'payment-gateway-views');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'payment-gateway-migrations');
    }

    protected function registerLivewire(): void
    {
        Livewire::component('payment.gateways', ManageGateways::class);
        Livewire::component('payment.gateways.edit', EditGateway::class);
        Livewire::component('payment.coupons', ManageCoupons::class);
        Livewire::component('payment.coupons.create', CreateCoupon::class);
        Livewire::component('payment.coupons.edit', EditCoupon::class);
        Livewire::component('payment.coupons.show', ShowCoupon::class);
        Livewire::component('payment.transactions', ManageTransactions::class);
        Livewire::component('payment.checkout', Checkout::class);
    }

    protected function registerRoutes(): void
    {
        if (config('payment-gateway.routes.register', true)) {
            Route::group([
                'prefix' => config('payment-gateway.routes.prefix', 'payment'),
                'middleware' => config('payment-gateway.routes.middleware', ['web']),
            ], function (): void {
                $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
            });
        }

        if (config('payment-gateway.routes.register_api', true)) {
            Route::group([
                'prefix' => config('payment-gateway.routes.api_prefix', 'api/v1/payment'),
                'middleware' => config('payment-gateway.routes.api_middleware', ['api']),
            ], function (): void {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });
        }
    }

    protected function registerNavigation(): void
    {
        $this->app->bind('payment.navigation.items', fn () => [
            [
                'label' => __('Gateways'),
                'icon' => 'credit-card',
                'route' => config('payment-gateway.routes.names.gateways', 'admin.payment.gateways'),
                'ability' => 'viewAny',
                'model' => Gateway::class,
                'group' => config('payment-gateway.navigation.group', __('Payments')),
                'sort' => 60,
            ],
            [
                'label' => __('Coupons'),
                'icon' => 'ticket',
                'route' => config('payment-gateway.routes.names.coupons', 'admin.payment.coupons'),
                'ability' => 'viewAny',
                'model' => Coupon::class,
                'group' => config('payment-gateway.navigation.group', __('Payments')),
                'sort' => 61,
            ],
            [
                'label' => __('Transactions'),
                'icon' => 'banknotes',
                'route' => 'admin.payment.transactions',
                'ability' => 'viewAny',
                'model' => Payment::class,
                'group' => config('payment-gateway.navigation.group', __('Payments')),
                'sort' => 62,
            ],
        ]);

        $this->app->tag('payment.navigation.items', 'admin.navigation');
    }

    /**
     * @return list<string>
     */
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
