<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jose\Component\Core\JWK;
use RuntimeException;
use Throwable;

/**
 * Thin client over 2C2P PACO's Core Payment API (Himalayan Bank). Every call is a signed
 * + encrypted JOSE request (`application/jose`) and a signed + encrypted JOSE
 * response, verified via JoseCodec.
 */
class HblClient
{
    public function __construct(private readonly JoseCodec $codec) {}

    /**
     * Start a hosted-checkout session. Returns the decoded response, including
     * `response.Data.paymentPage.paymentPageURL`.
     *
     * @param array<string, mixed> $request
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
     * @param array<string, mixed> $advSearchParams
     * @return array<string, mixed>
     */
    public function transactionList(array $advSearchParams): array
    {
        return $this->send('api/1.0/Inquiry/transactionList', [
            'advSearchParams' => $advSearchParams,
        ]);
    }

    /**
     * Void an authorised (not yet settled) payment. Expects the documented
     * shape: officeId, orderNo, productDescription, issuerApprovalCode (the
     * ApprovalCode from the payment response), actionBy, voidAmount{…}.
     * An orderNo can be voided/refunded only once.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function void(array $request): array
    {
        return $this->send('api/1.0/Void', $request);
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function send(string $path, array $request): array
    {
        $apiKey = (string) HblConfig::get('api_key');

        if (blank($apiKey) || blank(HblConfig::get('office_id'))) {
            throw new RuntimeException('HBL gateway is not configured (missing office_id/api_key).');
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

        $response = Http::withBody($body, 'application/jose; charset=utf-8')
            ->withHeaders([
                'Accept' => 'application/jose',
                'CompanyApiKey' => $apiKey,
            ])
            ->baseUrl(rtrim((string) HblConfig::get('base_url'), '/') . '/')
            ->timeout(30)
            ->connectTimeout(10)
            ->throw()
            ->post($path)
            ->body();

        return $this->codec->decrypt($response, $this->decryptionKey(), $this->pacoSigningKey(), $apiKey);
    }

    private function signingKey(): JWK
    {
        return $this->codec->loadPrivateKey($this->readKeyValue('merchant_signing_key', 'merchant_signing_key_path'));
    }

    private function decryptionKey(): JWK
    {
        return $this->codec->loadPrivateKey($this->readKeyValue('merchant_decryption_key', 'merchant_decryption_key_path'));
    }

    private function pacoEncryptionKey(): JWK
    {
        return $this->codec->loadPublicKey($this->readKeyValue('paco_encryption_public_key', 'paco_encryption_public_key_path'));
    }

    private function pacoSigningKey(): JWK
    {
        return $this->codec->loadPublicKey($this->readKeyValue('paco_signing_public_key', 'paco_signing_public_key_path'));
    }

    private function readKeyValue(string $newKey, string $oldKey): string
    {
        $value = $this->readKeyFile($oldKey);
        if (filled($value)) {
            return $value;
        }
        return $this->readKeyFile($newKey);
    }

    private function readKeyFile(string $configKey): string
    {
        // Try database gateway first (typed private storage)
        if (app()->bound(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class)) {
            try {
                $resolver = app(\Kreetancraft\PaymentGateway\Contracts\GatewayResolver::class);
                $gateway = $resolver->getGatewayConfigModel('himalayan');
                if ($gateway) {
                    $raw = $gateway->getCredential($configKey);
                    if (filled($raw)) {
                        return $this->resolveKeyValue((string) $raw);
                    }
                }
            } catch (\Throwable) {
                // fallback to HblConfig
            }
        }

        $path = HblConfig::keyPath($configKey);

        if (blank($path)) {
            throw new RuntimeException("HBL JOSE key path is not configured ({$configKey}).");
        }

        return $this->resolveKeyValue($path);
    }

    private function resolveKeyValue(string $value): string
    {
        $value = trim($value);

        // Typed raw key (PEM or base64) — use directly, no file IO
        if (str_contains($value, '-----BEGIN')) {
            return $value;
        }

        // If value looks like base64 key (>100 chars, no path separators), return as-is
        if (strlen($value) > 200 && ! str_contains($value, '/') && ! str_contains($value, '\\')) {
            return $value;
        }

        // Private storage: storage/app/private/hbl/xxx.key
        $privatePath = storage_path('app/private/' . ltrim($value, '/'));
        if (is_file($privatePath)) {
            try {
                return File::get($privatePath);
            } catch (Throwable) {
            }
        }

        // Storage disk private
        try {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists('private/' . ltrim($value, '/'))) {
                return \Illuminate\Support\Facades\Storage::disk('local')->get('private/' . ltrim($value, '/'));
            }
        } catch (Throwable) {
        }

        // Fallback: treat as file path
        try {
            return File::get($value);
        } catch (Throwable) {
            // If not a file, assume raw key material
            return $value;
        }
    }
}
