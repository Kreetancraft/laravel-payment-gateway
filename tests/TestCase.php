<?php

namespace Kreetancraft\PaymentGateway\Tests;

use Flux\FluxServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Kreetancraft\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Kreetancraft\PaymentGateway\Tests\Fixtures\Models\TestInvoice;
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

        // Pinned, so the suite does not inherit whatever testbench.yaml sets for
        // the workbench. Laravel's default store is the database, and there is no
        // cache table here — the gateway resolver caches its enabled list, so
        // that surfaces as "no such table: cache" from unrelated tests.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('payment-gateway.routes.home', '/');

        // The only payable the suite knows about. Checkout refuses anything not
        // listed here, so this is also what makes the allowlist testable.
        $app['config']->set('payment-gateway.payables', [
            'invoice' => TestInvoice::class,
        ]);
        $app['config']->set('view.paths', [
            __DIR__.'/fixtures/views',
            __DIR__.'/../resources/views',
            resource_path('views'),
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // The host's table, standing in for whatever it sells.
        Schema::create('test_invoices', function ($table): void {
            $table->id();
            $table->string('number');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('total_cents')->default(0);
            $table->unsignedInteger('paid_cents')->default(0);
            $table->unsignedInteger('deposit_cents')->default(0);
            $table->timestamps();
        });
    }

    protected function seedRolesAndPermissions(): void
    {
        // Hook for permission seeding in tests if spatie/laravel-permission is installed
    }
}
