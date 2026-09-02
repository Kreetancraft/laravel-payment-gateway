<?php

use Illuminate\Support\Facades\Config;
use Kreetancraft\PaymentGateway\Support\GatewayEncrypter;

it('encrypts and decrypts array payloads correctly with app key fallback', function (): void {
    $data = [
        'secret_key' => 'sk_live_test123456',
        'publishable_key' => 'pk_live_test123456',
        'webhook_secret' => 'whsec_test123456',
    ];

    $encrypted = GatewayEncrypter::encrypt($data);

    expect($encrypted)->toBeString()
        ->and($encrypted)->not->toContain('sk_live_test123456');

    $decrypted = GatewayEncrypter::decrypt($encrypted);

    expect($decrypted)->toBeArray()
        ->and($decrypted['secret_key'])->toBe('sk_live_test123456')
        ->and($decrypted['publishable_key'])->toBe('pk_live_test123456')
        ->and($decrypted['webhook_secret'])->toBe('whsec_test123456');
});

it('encrypts and decrypts with dedicated PAYMENT_GATEWAY_ENCRYPTION_KEY', function (): void {
    $customKey = 'base64:'.base64_encode(random_bytes(32));
    Config::set('payment-gateway.encryption.key', $customKey);

    $data = [
        'office_id' => '9104137120',
        'api_key' => 'custom_hbl_api_key_xyz',
        'merchant_signing_key' => '-----BEGIN RSA PRIVATE KEY-----MIIE...',
    ];

    $encrypted = GatewayEncrypter::encrypt($data);

    expect($encrypted)->toBeString()
        ->and($encrypted)->not->toContain('custom_hbl_api_key_xyz');

    $decrypted = GatewayEncrypter::decrypt($encrypted);

    expect($decrypted)->toBeArray()
        ->and($decrypted['office_id'])->toBe('9104137120')
        ->and($decrypted['api_key'])->toBe('custom_hbl_api_key_xyz')
        ->and($decrypted['merchant_signing_key'])->toBe('-----BEGIN RSA PRIVATE KEY-----MIIE...');
});

it('returns empty array when decrypting null or empty string', function (): void {
    expect(GatewayEncrypter::decrypt(null))->toBe([])
        ->and(GatewayEncrypter::decrypt(''))->toBe([]);
});

it('returns empty array gracefully on invalid or corrupted ciphertext', function (): void {
    $corrupted = 'eyJpdiI6ImludmFsaWQiLCJ2YWx1ZSI6ImludmFsaWQiLCJtYWMiOiJpbnZhbGlkIn0=';

    expect(GatewayEncrypter::decrypt($corrupted))->toBe([]);
});
