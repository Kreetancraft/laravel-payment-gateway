<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreetancraft\PaymentGateway\Gateways\HimalayanBankGateway;
use Kreetancraft\PaymentGateway\Gateways\StripeGateway;
use Kreetancraft\PaymentGateway\Models\Gateway;

uses(RefreshDatabase::class);

it('stores and retrieves encrypted credentials via attribute accessor', function (): void {
    $gateway = Gateway::create([
        'code' => 'stripe',
        'label' => 'Stripe',
        'enabled' => true,
        'class' => StripeGateway::class,
        'credentials' => [
            'secret_key' => 'sk_test_secret_123',
            'publishable_key' => 'pk_test_pub_123',
            'webhook_secret' => 'whsec_test_123',
        ],
    ]);

    // Check that the raw column in database is encrypted ciphertext
    $rawAttributes = $gateway->getAttributes();
    expect($rawAttributes['credentials'])->toBeString()
        ->and($rawAttributes['credentials'])->not->toContain('sk_test_secret_123');

    // Check that model attribute access decrypts properly
    $fresh = Gateway::find($gateway->id);
    expect($fresh->getStripeSecretKey())->toBe('sk_test_secret_123')
        ->and($fresh->getStripePublishableKey())->toBe('pk_test_pub_123')
        ->and($fresh->getStripeWebhookSecret())->toBe('whsec_test_123');
});

it('manages typed getters and setters for Himalayan Bank credentials', function (): void {
    $gateway = new Gateway([
        'code' => 'himalayan',
        'label' => 'Himalayan Bank',
        'enabled' => true,
        'class' => HimalayanBankGateway::class,
    ]);

    $gateway->setHimalayanOfficeId('9104137120');
    $gateway->setHimalayanApiKey('hbl_api_key_abc');
    $gateway->setHimalayanEncryptionKeyId('hbl_enc_id_123');
    $gateway->setMerchantSigningKey('PEM_SIGNING_KEY_MATERIAL');
    $gateway->setMerchantDecryptionKey('PEM_DECRYPTION_KEY_MATERIAL');
    $gateway->setPacoEncryptionPublicKey('PACO_ENC_KEY_MATERIAL');
    $gateway->setPacoSigningPublicKey('PACO_SIGN_KEY_MATERIAL');
    $gateway->setHimalayanEnvironment('production');
    $gateway->save();

    $fresh = Gateway::where('code', 'himalayan')->first();

    expect($fresh->getHimalayanOfficeId())->toBe('9104137120')
        ->and($fresh->getHimalayanApiKey())->toBe('hbl_api_key_abc')
        ->and($fresh->getHimalayanEncryptionKeyId())->toBe('hbl_enc_id_123')
        ->and($fresh->getMerchantSigningKey())->toBe('PEM_SIGNING_KEY_MATERIAL')
        ->and($fresh->getMerchantDecryptionKey())->toBe('PEM_DECRYPTION_KEY_MATERIAL')
        ->and($fresh->getPacoEncryptionPublicKey())->toBe('PACO_ENC_KEY_MATERIAL')
        ->and($fresh->getPacoSigningPublicKey())->toBe('PACO_SIGN_KEY_MATERIAL')
        ->and($fresh->getHimalayanEnvironment())->toBe('production');
});

it('validates isConfigured based on required config fields', function (): void {
    $gateway = Gateway::create([
        'code' => 'stripe',
        'label' => 'Stripe',
        'enabled' => true,
        'class' => StripeGateway::class,
        'config_fields' => [
            ['key' => 'secret_key', 'required' => true],
            ['key' => 'publishable_key', 'required' => true],
            ['key' => 'webhook_secret', 'required' => false],
        ],
        'credentials' => [
            'secret_key' => 'sk_test_123',
        ],
    ]);

    // Missing required publishable_key
    expect($gateway->isConfigured())->toBeFalse();

    // Fill required publishable_key
    $gateway->setCredential('publishable_key', 'pk_test_123');
    $gateway->save();

    expect($gateway->fresh()->isConfigured())->toBeTrue();
});

it('verifies supported currencies correctly', function (): void {
    $gateway = new Gateway([
        'currencies' => ['USD', 'NPR', 'EUR'],
    ]);

    expect($gateway->supportsCurrency('usd'))->toBeTrue()
        ->and($gateway->supportsCurrency('NPR'))->toBeTrue()
        ->and($gateway->supportsCurrency('eur'))->toBeTrue()
        ->and($gateway->supportsCurrency('JPY'))->toBeFalse();
});

it('filters enabled and environment scopes', function (): void {
    Gateway::create([
        'code' => 'gw_1',
        'label' => 'Gateway 1',
        'enabled' => true,
        'environment' => 'demo',
        'class' => StripeGateway::class,
    ]);

    Gateway::create([
        'code' => 'gw_2',
        'label' => 'Gateway 2',
        'enabled' => false,
        'environment' => 'production',
        'class' => StripeGateway::class,
    ]);

    expect(Gateway::query()->enabled()->count())->toBe(1)
        ->and(Gateway::query()->environment('production')->count())->toBe(1);
});
