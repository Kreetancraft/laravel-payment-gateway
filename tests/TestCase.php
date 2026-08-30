<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Tests;

use Illuminate\Foundation\Application;
use Kreetancraft\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            PaymentGatewayServiceProvider::class,
        ];
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
        $app['config']->set('payment-gateway.webhook.verify_signature', false);
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
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
