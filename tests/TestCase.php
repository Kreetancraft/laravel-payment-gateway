<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Tests;

use Flux\FluxServiceProvider;
use Illuminate\Foundation\Application;
use Kreetancraft\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        $providers = [
            LivewireServiceProvider::class,
            PaymentGatewayServiceProvider::class,
        ];

        if (class_exists(FluxServiceProvider::class)) {
            $providers[] = FluxServiceProvider::class;
        }

        return $providers;
    }

    protected function getEnvironmentSetUp($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('payment-gateway.gateways.stripe.secret_key', 'sk_test_mock');
        $app['config']->set('payment-gateway.gateways.stripe.publishable_key', 'pk_test_mock');
        $app['config']->set('payment-gateway.gateways.stripe.webhook_secret', 'whsec_test_mock');
        $app['config']->set('payment-gateway.gateways.stripe.enabled', true);
        $app['config']->set('payment-gateway.gateways.himalayan.enabled', false);
        $app['config']->set('payment-gateway.gateways.himalayan.office_id', null);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('payment-gateway.routes.home', '/');
        $app['config']->set('view.paths', [
            __DIR__.'/fixtures/views',
            __DIR__.'/../resources/views',
            resource_path('views'),
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function seedRolesAndPermissions(): void
    {
        // Hook for permission seeding in tests if spatie/laravel-permission is installed
    }
}
