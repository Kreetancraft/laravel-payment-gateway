<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Support;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Dedicated encrypter for database-stored payment gateway credentials and configs.
 *
 * Uses PAYMENT_GATEWAY_ENCRYPTION_KEY or PAYMENT_GATEWAY_SECRET from .env
 * (falling back to APP_KEY) so that even if the database is compromised,
 * all payment secrets, private keys, and API tokens remain securely encrypted.
 */
class GatewayEncrypter
{
    private static ?Encrypter $encrypter = null;

    public static function getEncrypter(): Encrypter
    {
        if (self::$encrypter !== null) {
            return self::$encrypter;
        }

        $key = (string) config(
            'payment-gateway.encryption.key',
            env('PAYMENT_GATEWAY_ENCRYPTION_KEY', env('PAYMENT_GATEWAY_SECRET', ''))
        );

        $cipher = (string) config('payment-gateway.encryption.cipher', 'AES-256-CBC');

        if (blank($key)) {
            // Fallback to standard Laravel app encrypter
            return app('encrypter');
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        self::$encrypter = new Encrypter($key, $cipher);

        return self::$encrypter;
    }

    /**
     * Encrypt array credentials/config into encrypted payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function encrypt(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return self::getEncrypter()->encryptString($json);
    }

    /**
     * Decrypt encrypted payload into array.
     *
     * @return array<string, mixed>
     */
    public static function decrypt(?string $payload): array
    {
        if (blank($payload)) {
            return [];
        }

        try {
            $decrypted = self::getEncrypter()->decryptString($payload);

            return (array) (json_decode($decrypted, true) ?? []);
        } catch (Throwable) {
            // Fallback attempt with standard Crypt in case standard encryption was used
            try {
                $decrypted = Crypt::decryptString($payload);

                return (array) (json_decode($decrypted, true) ?? []);
            } catch (Throwable) {
                return [];
            }
        }
    }
}
