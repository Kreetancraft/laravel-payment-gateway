<?php

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kreetancraft\PaymentGateway\Database\Factories\GatewayFactory;
use Kreetancraft\PaymentGateway\Support\GatewayEncrypter;

/**
 * Gateway model with secure database-stored encrypted credentials.
 *
 * Sensitive credentials (API keys, secrets, private keys, certificates)
 * are stored in the database as encrypted ciphertext using GatewayEncrypter
 * (via PAYMENT_GATEWAY_ENCRYPTION_KEY or APP_KEY in .env).
 *
 * @property int $id
 * @property string $code
 * @property string $label
 * @property string|null $icon
 * @property bool $enabled
 * @property string $class
 * @property array|null $currencies
 * @property array|null $capabilities
 * @property bool $checkout_redirect
 * @property bool $supports_subscriptions
 * @property string $environment
 * @property array $credentials
 * @property array|null $config_fields
 */
class Gateway extends Model
{
    use HasFactory;

    protected $table = 'payment_gateways';

    protected $guarded = ['id'];

    protected $fillable = [
        'code',
        'label',
        'icon',
        'enabled',
        'class',
        'currencies',
        'capabilities',
        'checkout_redirect',
        'supports_subscriptions',
        'environment',
        'credentials',
        'config_fields',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'enabled' => 'boolean',
            'checkout_redirect' => 'boolean',
            'supports_subscriptions' => 'boolean',
            'currencies' => 'array',
            'capabilities' => 'array',
            'config_fields' => 'array',
            'environment' => 'string',
        ];
    }

    protected static function newFactory(): GatewayFactory
    {
        return GatewayFactory::new();
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        return ($this->credentials ?? [])[$key] ?? $default;
    }

    public function setCredential(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->getCredential($key, $default);
    }

    public function getStripeSecretKey(): ?string
    {
        return $this->getCredential('secret_key');
    }

    public function setStripeSecretKey(string $value): void
    {
        $this->setCredential('secret_key', $value);
    }

    public function getStripePublishableKey(): ?string
    {
        return $this->getCredential('publishable_key');
    }

    public function setStripePublishableKey(string $value): void
    {
        $this->setCredential('publishable_key', $value);
    }

    public function getStripeWebhookSecret(): ?string
    {
        return $this->getCredential('webhook_secret');
    }

    public function setStripeWebhookSecret(string $value): void
    {
        $this->setCredential('webhook_secret', $value);
    }

    public function getHimalayanOfficeId(): ?string
    {
        return $this->getCredential('office_id');
    }

    public function setHimalayanOfficeId(string $value): void
    {
        $this->setCredential('office_id', $value);
    }

    public function getHimalayanApiKey(): ?string
    {
        return $this->getCredential('api_key');
    }

    public function setHimalayanApiKey(string $value): void
    {
        $this->setCredential('api_key', $value);
    }

    public function getHimalayanEncryptionKeyId(): ?string
    {
        return $this->getCredential('encryption_key_id');
    }

    public function setHimalayanEncryptionKeyId(string $value): void
    {
        $this->setCredential('encryption_key_id', $value);
    }

    public function getHimalayanEnvironment(): string
    {
        return (string) $this->getCredential('environment', 'demo');
    }

    public function setHimalayanEnvironment(string $value): void
    {
        $this->setCredential('environment', $value);
    }

    /**
     * Should PACO be asked to run 3-D Secure on this payment?
     *
     * The admin screen labels this "Y/N", and `filter_var()` understands
     * neither. It knows 1/0, true/false, yes/no and on/off; given 'N' it
     * returns null, and the `?? true` that used to follow turned that into
     * "yes". So the setting was write-only — every payment requested 3DS
     * whatever the admin chose, and on a profile that cannot complete 3DS the
     * card was submitted and then rejected before the acquirer was ever
     * contacted. The vendor's own working demo only ever sends 'N'.
     *
     * `true` remains the default when the credential is simply absent, because
     * asking for 3DS is the safer thing not to forget. A value that is present
     * but unreadable is a configuration mistake, and quietly turning it on is
     * how the mistake stayed hidden — so that case is now false.
     */
    public function getHimalayanRequest3ds(): bool
    {
        $value = $this->getCredential('request_3ds');

        if ($value === null || $value === '') {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['y', 'yes', 'true', '1', 'on'], true);
    }

    public function setHimalayanRequest3ds(bool $value): void
    {
        $this->setCredential('request_3ds', $value);
    }

    public function isHimalayanTestMode(): bool
    {
        return strtolower($this->getHimalayanEnvironment()) !== 'production' && strtolower($this->getHimalayanEnvironment()) !== 'live';
    }

    public function getMerchantSigningKey(): ?string
    {
        return $this->getCredential('merchant_signing_key') ?? $this->getCredential('merchant_signing_key_path');
    }

    public function setMerchantSigningKey(string $value): void
    {
        $this->setCredential('merchant_signing_key', $value);
    }

    public function getMerchantSigningKeyPath(): ?string
    {
        return $this->getMerchantSigningKey();
    }

    public function setMerchantSigningKeyPath(string $value): void
    {
        $this->setMerchantSigningKey($value);
    }

    public function getMerchantDecryptionKey(): ?string
    {
        return $this->getCredential('merchant_decryption_key') ?? $this->getCredential('merchant_decryption_key_path');
    }

    public function setMerchantDecryptionKey(string $value): void
    {
        $this->setCredential('merchant_decryption_key', $value);
    }

    public function getMerchantDecryptionKeyPath(): ?string
    {
        return $this->getMerchantDecryptionKey();
    }

    public function setMerchantDecryptionKeyPath(string $value): void
    {
        $this->setMerchantDecryptionKey($value);
    }

    public function getPacoEncryptionPublicKey(): ?string
    {
        return $this->getCredential('paco_encryption_public_key') ?? $this->getCredential('paco_encryption_public_key_path');
    }

    public function setPacoEncryptionPublicKey(string $value): void
    {
        $this->setCredential('paco_encryption_public_key', $value);
    }

    public function getPacoEncryptionPublicKeyPath(): ?string
    {
        return $this->getPacoEncryptionPublicKey();
    }

    public function setPacoEncryptionPublicKeyPath(string $value): void
    {
        $this->setPacoEncryptionPublicKey($value);
    }

    public function getPacoSigningPublicKey(): ?string
    {
        return $this->getCredential('paco_signing_public_key') ?? $this->getCredential('paco_signing_public_key_path');
    }

    public function setPacoSigningPublicKey(string $value): void
    {
        $this->setCredential('paco_signing_public_key', $value);
    }

    public function getPacoSigningPublicKeyPath(): ?string
    {
        return $this->getPacoSigningPublicKey();
    }

    public function setPacoSigningPublicKeyPath(string $value): void
    {
        $this->setPacoSigningPublicKey($value);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDisplayLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): string
    {
        return $this->icon ?? '';
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getClassName(): string
    {
        return $this->class;
    }

    /**
     * @return list<string>
     */
    public function getSupportedCurrencies(): array
    {
        return (array) ($this->currencies ?? []);
    }

    /**
     * @return list<string>
     */
    public function getCapabilities(): array
    {
        return (array) ($this->capabilities ?? []);
    }

    public function usesCheckoutRedirect(): bool
    {
        return (bool) $this->checkout_redirect;
    }

    public function supportsSubscriptions(): bool
    {
        return (bool) $this->supports_subscriptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigFields(): array
    {
        return (array) ($this->config_fields ?? []);
    }

    public function getEnvironment(): string
    {
        return $this->environment ?? 'demo';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    public function supportsCurrency(string $currency): bool
    {
        $currencies = $this->getSupportedCurrencies();

        if (empty($currencies)) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', $currencies), true);
    }

    public function isConfigured(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        foreach ($this->getRequiredConfigFields() as $field) {
            if (blank($this->getCredential($field))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    protected function getRequiredConfigFields(): array
    {
        return collect($this->getConfigFields())
            ->where('required', true)
            ->pluck('key')
            ->map(fn ($key) => (string) $key)
            ->all();
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }
}
