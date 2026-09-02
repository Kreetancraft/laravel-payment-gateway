<?php

namespace Kreetancraft\PaymentGateway\Support;

use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Clean encryption helper for database-stored payment gateway credentials and configs.
 */
class GatewayEncrypter
{
    /**
     * Encrypt array credentials/config into encrypted payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function encrypt(array $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);

        return Crypt::encryptString($json);
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
            $decrypted = Crypt::decryptString($payload);

            return (array) (json_decode($decrypted, true) ?? []);
        } catch (Throwable) {
            return [];
        }
    }
}
