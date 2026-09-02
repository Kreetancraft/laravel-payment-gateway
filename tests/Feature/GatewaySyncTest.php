<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;

uses(RefreshDatabase::class);

/**
 * `payment-gateway:sync --to-database` used to destroy every stored secret.
 *
 * Credentials are pasted in through the admin screen and live only in the
 * database — config ships an empty array for them, deliberately, because keys
 * do not belong in a repository. The command passed `$config['credentials'] ??
 * []` straight into updateOrCreate, so a sync overwrote the real credentials
 * with nothing.
 *
 * There was no error and no confirmation. The gateway simply stopped working,
 * and the only copy of the keys was gone. Found by running the command on the
 * bench and watching a configured gateway start failing.
 */
it('keeps credentials that were entered through the admin screen', function (): void {
    Gateway::create([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['USD'],
        'credentials' => [
            'office_id' => '9104137120',
            'api_key' => 'a-real-key',
            'encryption_key_id' => 'a-real-kid',
        ],
    ]);

    Artisan::call('payment-gateway:sync', ['--to-database' => true]);

    $credentials = Gateway::where('code', 'himalayan')->first()->credentials;

    expect($credentials['office_id'])->toBe('9104137120')
        ->and($credentials['api_key'])->toBe('a-real-key')
        ->and($credentials['encryption_key_id'])->toBe('a-real-kid');
});

it('still refreshes the parts that do come from config', function (): void {
    // The command has a job to do — this is not "make sync a no-op".
    Gateway::create([
        'code' => 'himalayan',
        'label' => 'Stale label',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
        'currencies' => ['XXX'],
        'credentials' => ['office_id' => 'keep-me'],
    ]);

    Artisan::call('payment-gateway:sync', ['--to-database' => true]);

    $gateway = Gateway::where('code', 'himalayan')->first();

    expect($gateway->label)->toBe(config('payment-gateway.gateways.himalayan.label'))
        ->and($gateway->credentials['office_id'])->toBe('keep-me');
});
