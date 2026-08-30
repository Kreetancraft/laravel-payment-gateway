<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jose\Component\Core\JWK;
use Kreetancraft\PaymentGateway\Contracts\GatewayResolver;
use RuntimeException;
use Throwable;

/**
 * Thin client over 2C2P PACO's Core Payment API (Himalayan Bank).
 * Every call is a signed + encrypted JOSE request (`application/jose`) and a signed + encrypted
 * JOSE response, verified via JoseCodec.
 *
 * All RSA keys and credentials are read directly from encrypted database storage (zero filesystem IO).
 */
class HblClient
{
    public function __construct(private readonly JoseCodec $codec) {}

    /**
     * Start a hosted-checkout session. Returns the decoded response, including
     * `response.Data.paymentPage.paymentPageURL`.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function prePaymentUi(array $request): array
    {
        return $this->send('api/1.0/Payment/prePaymentUi', $request);
    }

    /**
     * Authoritative transaction status lookup. Returns the decoded response,
     * including `response.Data` (a list of matching transactions).
     *
     * @param  array<string, mixed>  $advSearchParams
     * @return array<string, mixed>
     */
    public function transactionList(array $advSearchParams): array
    {
        return $this->send('api/1.0/Inquiry/transactionList', [
            'advSearchParams' => $advSearchParams,
        ]);
    }

    /**
     * Void an authorised (not yet settled) payment.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function void(array $request): array
    {
        return $this->send('api/1.0/Void', $request);
    }

    private function getOfficeId(): string
    {
        if (app()->bound(GatewayResolver::class)) {
            try {
                $gateway = app(GatewayResolver::class)->getGatewayConfigModel('himalayan');
                if ($gateway && filled($gateway->getHimalayanOfficeId())) {
                    return (string) $gateway->getHimalayanOfficeId();
                }
            } catch (Throwable) {
            }
        }

        return (string) HblConfig::get('office_id', '');
    }

    private function getApiKey(): string
    {
        if (app()->bound(GatewayResolver::class)) {
            try {
                $gateway = app(GatewayResolver::class)->getGatewayConfigModel('himalayan');
                if ($gateway && filled($gateway->getHimalayanApiKey())) {
                    return (string) $gateway->getHimalayanApiKey();
                }
            } catch (Throwable) {
            }
        }

        return (string) HblConfig::get('api_key', '');
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function send(string $path, array $request): array
    {
        $apiKey = $this->getApiKey();
        $officeId = $this->getOfficeId();

        if (blank($apiKey) || blank($officeId)) {
            throw new RuntimeException('HBL gateway is not configured in database (missing office_id/api_key).');
        }

        $request = array_merge([
            'apiRequest' => [
                'requestMessageID' => (string) Str::uuid(),
                'requestDateTime' => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
                'language' => 'en-US',
            ],
        ], $request);

        $now = now();

        $payload = [
            'request' => $request,
            'iss' => $apiKey,
            'aud' => 'PacoAudience',
            'CompanyApiKey' => $apiKey,
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $now->copy()->addHour()->timestamp,
        ];

        $body = $this->codec->encrypt($payload, $this->signingKey(), $this->pacoEncryptionKey());

        $baseUrl = rtrim((string) (HblConfig::get('base_url') ?: 'https://core.demo-paco.2c2p.com/'), '/');

        $response = Http::withBody($body, 'application/jose; charset=utf-8')
            ->withHeaders([
                'Accept' => 'application/jose',
                'CompanyApiKey' => $apiKey,
            ])
            ->baseUrl("{$baseUrl}/")
            ->timeout(30)
            ->connectTimeout(10)
            ->throw()
            ->post($path)
            ->body();

        return $this->codec->decrypt($response, $this->decryptionKey(), $this->pacoSigningKey(), $apiKey);
    }

    private function signingKey(): JWK
    {
        return $this->codec->loadPrivateKey($this->getKeyMaterial('merchant_signing_key'));
    }

    private function decryptionKey(): JWK
    {
        return $this->codec->loadPrivateKey($this->getKeyMaterial('merchant_decryption_key'));
    }

    private function pacoEncryptionKey(): JWK
    {
        return $this->codec->loadPublicKey($this->getKeyMaterial('paco_encryption_public_key'));
    }

    private function pacoSigningKey(): JWK
    {
        return $this->codec->loadPublicKey($this->getKeyMaterial('paco_signing_public_key'));
    }

    /**
     * Read raw PEM key string directly from encrypted database storage (zero filesystem dependency).
     */
    private function getKeyMaterial(string $keyName): string
    {
        // 1. Read from database gateway model
        if (app()->bound(GatewayResolver::class)) {
            try {
                $gateway = app(GatewayResolver::class)->getGatewayConfigModel('himalayan');
                if ($gateway) {
                    $raw = $gateway->getCredential($keyName);
                    if (filled($raw)) {
                        return trim((string) $raw);
                    }

                    // Fallback to legacy key name with _path suffix if present
                    $legacyRaw = $gateway->getCredential("{$keyName}_path");
                    if (filled($legacyRaw)) {
                        return trim((string) $legacyRaw);
                    }
                }
            } catch (Throwable) {
            }
        }

        // 2. Fallback to runtime config
        $configValue = HblConfig::get($keyName) ?? HblConfig::get("{$keyName}_path");
        if (filled($configValue)) {
            return trim((string) $configValue);
        }

        throw new RuntimeException("HBL JOSE key [{$keyName}] is not configured in database.");
    }
}
