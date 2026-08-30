<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Kreetancraft\PaymentGateway\Database\Factories\GatewayFactory;

/**
 * Gateway model with encrypted credentials storage.
 * 
 * Sensitive credentials (API keys, secrets, keys, certificates) are stored 
 * as encrypted JSON using Laravel's built-in encryption. Individual sensitive
 * fields can be accessed via typed getters/setters.
 */
class Gateway extends Model
{
    use HasFactory;

    protected $table = 'payment_gateways';

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'checkout_redirect' => 'boolean',
        'supports_subscriptions' => 'boolean',
        'currencies' => 'array',
        'capabilities' => 'array',
        'config_fields' => 'array',
        'environment' => 'string',
        'credentials' => 'encrypted:array', // Encrypted sensitive data
    ];

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

    protected static function newFactory(): GatewayFactory
    {
        return GatewayFactory::new();
    }

    // ============================================
    // TYPED ACCESSORS FOR SENSITIVE CREDENTIALS
    // ============================================

    /**
     * Get decrypted credentials array.
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode(Crypt::decryptString($value), true) : [],
            set: fn ($value) => Crypt::encryptString(json_encode($value)),
        );
    }

    // ============================================
    // TYPED CREDENTIAL ACCESSORS
    // ============================================

    /**
     * Get Stripe secret key (sk_test_... or sk_live_...).
     */
    public function getStripeSecretKey(): ?string
    {
        return $this->getCredential('secret_key');
    }

    /**
     * Set Stripe secret key.
     */
    public function setStripeSecretKey(string $value): void
    {
        $this->setCredential('secret_key', $value);
    }

    /**
     * Get Stripe publishable key.
     */
    public function getStripePublishableKey(): ?string
    {
        return $this->getCredential('publishable_key');
    }

    /**
     * Set Stripe publishable key.
     */
    public function setStripePublishableKey(string $value): void
    {
        $this->setCredential('publishable_key', $value);
    }

    /**
     * Get Stripe webhook signing secret.
     */
    public function getStripeWebhookSecret(): ?string
    {
        return $this->getCredential('webhook_secret');
    }

    /**
     * Set Stripe webhook secret.
     */
    public function setStripeWebhookSecret(string $value): void
    {
        $this->setCredential('webhook_secret', $value);
    }

    /**
     * Get Himalayan Bank Office ID.
     */
    public function getHimalayanOfficeId(): ?string
    {
        return $this->getCredential('office_id');
    }

    /**
     * Set Himalayan Bank Office ID.
     */
    public function setHimalayanOfficeId(string $value): void
    {
        $this->setCredential('office_id', $value);
    }

    /**
     * Get Himalayan Bank API key.
     */
    public function getHimalayanApiKey(): ?string
    {
        return $this->getCredential('api_key');
    }

    /**
     * Set Himalayan Bank API key.
     */
    public function setHimalayanApiKey(string $value): void
    {
        $this->setCredential('api_key', $value);
    }

    /**
     * Get Himalayan Bank encryption key ID.
     */
    public function getHimalayanEncryptionKeyId(): ?string
    {
        return $this->getCredential('encryption_key_id');
    }

    /**
     * Set Himalayan Bank encryption key ID.
     */
    public function setHimalayanEncryptionKeyId(string $value): void
    {
        $this->setCredential('encryption_key_id', $value);
    }

    /**
     * Get merchant signing key path.
     */
    public function getMerchantSigningKeyPath(): ?string
    {
        return $this->getCredential('merchant_signing_key_path');
    }

    public function setMerchantSigningKeyPath(string $value): void
    {
        $this->setCredential('merchant_signing_key_path', $value);
    }

    /**
     * Get merchant decryption key path.
     */
    public function getMerchantDecryptionKeyPath(): ?string
    {
        return $this->getCredential('merchant_decryption_key_path');
    }

    public function setMerchantDecryptionKeyPath(string $value): void
    {
        $this->setCredential('merchant_decryption_key_path', $value);
    }

    /**
     * Get PACO encryption public key path.
     */
    public function getPacoEncryptionPublicKeyPath(): ?string
    {
        return $this->getCredential('paco_encryption_public_key_path');
    }

    public function setPacoEncryptionPublicKeyPath(string $value): void
    {
        $this->setCredential('paco_encryption_public_key_path', $value);
    }

    /**
     * Get PACO signing public key path.
     */
    public function getPacoSigningPublicKeyPath(): ?string
    {
        return $this->getCredential('paco_signing_public_key_path');
    }

    public function setPacoSigningPublicKeyPath(string $value): void
    {
        $this->setCredential('paco_signing_public_key_path', $value);
    }

    /**
     * Get Himalayan Bank environment (demo/production).
     */
    public function getHimalayanEnvironment(): string
    {
        return $this->getCredential('environment', 'demo');
    }

    public function setHimalayanEnvironment(string $value): void
    {
        $this->setCredential('environment', $value);
    }

    /**
     * Get a specific credential value (decrypted).
     */
    public function getCredential(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a credential value (automatically encrypted).
     */
    public function setCredential(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    // ============================================
    // TYPED CONFIG ACCESSORS
    // ============================================

    /**
     * Get a configuration value (decrypted).
     */
    public function getConfig(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Get a configuration value with default.
     */
    public function getConfigValue(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    // ============================================
    // TYPED ACCESSORS FOR COMMON FIELDS
    // ============================================

    /**
     * Get the gateway's label.
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the gateway's icon.
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get the gateway's class name.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Get the gateway's supported currencies.
     */
    public function getSupportedCurrencies(): array
    {
        return $this->currencies;
    }

    /**
     * Get the gateway's capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Check if gateway uses checkout redirect.
     */
    public function checkoutRedirect(): bool
    {
        return $this->checkout_redirect;
    }

    /**
     * Get the gateway's configuration fields definition.
     */
    public function getConfigFields(): array
    {
        return $this->config_fields;
    }

    /**
     * Get the gateway's environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Check if gateway is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get a specific credential value (decrypted).
     */
    public function getCredential(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a credential value (automatically encrypted).
     */
    public function setCredential(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Get decrypted credential value.
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? json_decode(Crypt::decryptString($value), true) : [],
            set: fn ($value) => Crypt::encryptString(json_encode($value)),
        );
    }

    /**
     * Get the gateway's environment.
     */
    public function getEnvironment(): string
    {
        return $this->environment ?? 'demo';
    }

    /**
     * Check if gateway supports a currency.
     */
    public function supportsCurrency(string $currency): bool
    {
        $currencies = $this->currencies;
        if (empty($currencies)) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', $currencies), true);
    }

    /**
     * Get the gateway's capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Check if gateway uses checkout redirect.
     */
    public function usesCheckoutRedirect(): bool
    {
        return $this->checkout_redirect;
    }

    /**
     * Get the gateway's configuration fields definition.
     */
    public function getConfigFields(): array
    {
        return $this->config_fields;
    }

    /**
     * Get a configuration value (decrypted).
     */
    public function getConfig(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Get a configuration value with default.
     */
    public function getConfigValue(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Scope: only enabled gateways.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: only active (enabled) gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: filter by environment.
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Check if gateway is configured (has required credentials).
     */
    public function isConfigured(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $requiredFields = $this->getRequiredConfigFields();
        
        foreach ($requiredFields as $field) {
            $value = $this->getCredential($field['key']);
            if (blank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get required configuration fields for this gateway.
     */
    protected function getRequiredConfigFields(): array
    {
        return collect($this->config_fields ?? [])
            ->where('required', true)
            ->pluck('key')
            ->all();
    }

    /**
     * Get the gateway's display label.
     */
    public function getDisplayLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the gateway's icon.
     */
    public function getIcon(): string
    {
        return $this->icon ?? '';
    }

    /**
     * Get the gateway's class name.
     */
    public function getClassName(): string
    {
        return $this->class;
    }

    /**
     * Check if gateway supports subscriptions.
     */
    public function supportsSubscriptions(): bool
    {
        return $this->supports_subscriptions;
    }

    /**
     * Check if gateway uses checkout redirect.
     */
    public function usesCheckoutRedirect(): bool
    {
        return $this->checkout_redirect;
    }

    /**
     * Get the gateway's capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->capabilities ?? [];
    }

    /**
     * Get the gateway's configuration fields.
     */
    public function getConfigFields(): array
    {
        return $this->config_fields;
    }

    /**
     * Get a configuration value (decrypted).
     */
    public function getConfig(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Get a configuration value with default.
     */
    public function getConfigValue(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Scope: only enabled gateways.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: only active (enabled) gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: filter by environment.
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }

    /**
     * Check if gateway is configured (has required credentials).
     */
    public function isConfigured(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $requiredFields = $this->getRequiredConfigFields();
        
        foreach ($requiredFields as $field) {
            $value = $this->getCredential($field['key']);
            if (blank($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get required configuration fields for this gateway.
     */
    protected function getRequiredConfigFields(): array
    {
        return collect($this->config_fields ?? [])
            ->where('required', true)
            ->pluck('key')
            ->all();
    }

    /**
     * Get the gateway's display label.
     */
    public function getDisplayLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the gateway's icon.
     */
    public function getIcon(): string
    {
        return $this->icon ?? '';
    }

    /**
     * Get the gateway's class name.
     */
    public function getClassName(): string
    {
        return $this->class;
    }

    /**
     * Check if gateway supports subscriptions.
     */
    public function supportsSubscriptions(): bool
    {
        return $this->supports_subscriptions;
    }

    /**
     * Check if gateway uses checkout redirect.
     */
    public function usesCheckoutRedirect(): bool
    {
        return $this->checkout_redirect;
    }

    /**
     * Get the gateway's capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->capabilities ?? [];
    }

    /**
     * Get the gateway's configuration fields.
     */
    public function getConfigFields(): array
    {
        return $this->config_fields;
    }

    /**
     * Get a configuration value (decrypted).
     */
    public function getConfig(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Get a configuration value with default.
     */
    public function getConfigValue(string $key, $default = null): mixed
    {
        $credentials = $this->credentials ?? [];
        return $credentials[$key] ?? $default;
    }

    /**
     * Set a configuration value (automatically encrypted).
     */
    public function setConfig(string $key, mixed $value): void
    {
        $credentials = $this->credentials ?? [];
        $credentials[$key] = $value;
        $this->credentials = $credentials;
    }

    /**
     * Scope: only enabled gateways.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: only active (enabled) gateways.
     */
    public function scopeActive($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope: filter by environment.
     */
    public function scopeEnvironment($query, string $environment)
    {
        return $query->where('environment', $environment);
    }
}