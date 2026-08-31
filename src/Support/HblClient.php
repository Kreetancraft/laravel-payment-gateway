<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $officeId = $this->getOfficeId();

        $params = array_merge([
            'controllerInternalID' => null,
            'officeId' => [$officeId],
            'invoiceNo2C2P' => null,
            'fromDate' => '0001-01-01T00:00:00',
            'toDate' => '0001-01-01T00:00:00',
            'amountFrom' => null,
            'amountTo' => null,
        ], $advSearchParams);

        if (isset($params['orderNo']) && is_string($params['orderNo'])) {
            $params['orderNo'] = [$params['orderNo']];
        }

        if (isset($params['officeId']) && is_string($params['officeId'])) {
            $params['officeId'] = [$params['officeId']];
        }

        return $this->send('api/1.0/Inquiry/transactionList', [
            'advSearchParams' => $params,
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

        $baseUrl = $this->getBaseUrl();

        if (app()->environment('local', 'development')) {
            Log::debug('HBL JOSE request', [
                'path' => $path,
                'baseUrl' => $baseUrl,
                'officeId' => substr($officeId, 0, 4).str_repeat('*', max(0, strlen($officeId) - 4)),
                'apiKey' => substr($apiKey, 0, 6).'***',
                'orderNo' => $request['orderNo'] ?? ($request['advSearchParams']['orderNo'] ?? null),
                'amountText' => $request['transactionAmount']['amountText'] ?? null,
                'currency' => $request['transactionAmount']['currencyCode'] ?? null,
            ]);
        }

        $httpResponse = Http::withBody($body, 'application/jose; charset=utf-8')
            ->withHeaders([
                'Accept' => 'application/jose',
                'CompanyApiKey' => $apiKey,
            ])
            ->baseUrl("{$baseUrl}/")
            ->timeout(60)
            ->connectTimeout(15)
            ->post($path);

        $responseBody = (string) $httpResponse->body();

        if (str_starts_with($responseBody, 'ey')) {
            try {
                $decrypted = $this->codec->decrypt($responseBody, $this->decryptionKey(), $this->pacoSigningKey(), $apiKey);

                if ($httpResponse->failed()) {
                    $errorMsg = data_get($decrypted, 'response.ErrorDetails.Message')
                        ?? data_get($decrypted, 'response.message')
                        ?? data_get($decrypted, 'error')
                        ?? "HTTP {$httpResponse->status()} from HBL gateway";

                    throw new RuntimeException("HBL API Error ({$httpResponse->status()}): {$errorMsg}");
                }

                return $decrypted;
            } catch (Throwable $e) {
                if ($httpResponse->failed()) {
                    // Avoid double-wrapping "HBL API Error: HBL API Error"
                    $msg = str_starts_with($e->getMessage(), 'HBL API Error') ? $e->getMessage() : "HBL API Error ({$httpResponse->status()}): {$e->getMessage()}";
                    throw new RuntimeException($msg, 0, $e);
                }
                throw $e;
            }
        }

        if ($httpResponse->failed()) {
            Log::error('HBL non-JOSE error', ['path' => $path, 'status' => $httpResponse->status(), 'body' => substr($responseBody, 0, 4000)]);
            throw new RuntimeException("HBL API Error ({$httpResponse->status()}): ".substr($responseBody, 0, 500));
        }

        return (array) json_decode($responseBody, true);
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

    private function getBaseUrl(): string
    {
        if (app()->bound(GatewayResolver::class)) {
            try {
                $gateway = app(GatewayResolver::class)->getGatewayConfigModel('himalayan');
                if ($gateway) {
                    $customUrl = $gateway->getCredential('base_url');
                    if (filled($customUrl)) {
                        return rtrim((string) $customUrl, '/');
                    }

                    $env = strtolower((string) ($gateway->getCredential('environment') ?? 'demo'));

                    return match ($env) {
                        'production', 'live' => 'https://core.paco.2c2p.com',
                        default => 'https://core.demo-paco.2c2p.com',
                    };
                }
            } catch (Throwable) {
            }
        }

        return rtrim(HblConfig::baseUrl(), '/');
    }
}
