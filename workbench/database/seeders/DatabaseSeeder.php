<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;
use Workbench\App\Models\DemoInvoice;

/**
 * Two gateway rows with no credentials, and something to buy.
 *
 * The rows exist so the admin screen has something to open; they are disabled
 * and empty on purpose. Credentials get pasted in through the UI — nothing
 * sensitive belongs in a public repository, not even a vendor's published
 * sandbox keys, which look exactly like leaked ones to a scanner.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // USD, and small. The HBL account takes roughly USD 1-10, and Stripe
        // test mode is happy with anything — so one small USD invoice exercises
        // both gateways without a currency switch in between.
        DemoInvoice::firstOrCreate(
            ['number' => 'DEMO-001'],
            ['currency' => 'USD', 'total_cents' => 500, 'paid_cents' => 0],
        );

        Gateway::firstOrCreate(['code' => 'himalayan'], [
            'label' => 'Himalayan Bank (2C2P PACO)',
            'class' => HimalayanBankGateway::class,
            'enabled' => false,
            'environment' => 'demo',
            'currencies' => ['USD'],
            'checkout_redirect' => true,
        ]);

        Gateway::firstOrCreate(['code' => 'stripe'], [
            'label' => 'Stripe',
            'class' => StripeGateway::class,
            'enabled' => false,
            'environment' => 'demo',
            'currencies' => ['USD'],
            'checkout_redirect' => true,
        ]);
    }
}
