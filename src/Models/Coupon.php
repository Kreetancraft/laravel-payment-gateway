<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Database\Factories\CouponFactory;

/**
 * Coupon model - handles all discount/coupon functionality
 * Combines features from binafy/laravel-discount, pixellair/laravel-discount-system, and discountify
 */
class Coupon extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'coupons';

    // All fields that can be mass assigned
    protected $fillable = [
        'code',           // Unique coupon code (e.g., "SAVE20")
        'name',           // Display name (e.g., "20% Off")
        'description',    // Optional description
        
        // Discount type and value
        'type',              // percentage, fixed, buy_x_get_y, tiered, free_shipping
        'value',             // Percentage (20) or fixed amount in cents (2000 = $20)
        'max_discount_amount', // Cap for percentage discounts (in cents)
        'min_order_amount',    // Minimum order amount in cents
        
        // Usage limits
        'usage_limit',          // Total times this coupon can be used
        'usage_limit_per_user', // Max uses per user
        'user_ids',             // Specific user IDs allowed (JSON array)
        'usage_count',          // How many times used
        
        // Validity dates
        'starts_at',     // When coupon becomes valid
        'expires_at',    // When coupon expires
        'usage_count',   // How many times used
        'is_active',     // Is this coupon active?
        
        // Advanced features
        'max_discount_amount',  // Cap for percentage discounts
        'min_order_amount',     // Minimum order amount in cents
        'conditions',      // JSON: buy_x_get_y, tiered, time windows, etc.
        'is_stackable',    // Can stack with other coupons
        'is_free_shipping', // Free shipping coupon
    ];

    // Automatic casting for proper types
    protected $casts = [
        'value' => 'integer',
        'max_discount_amount' => 'integer',
        'min_order_amount' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'user_ids' => 'array',        // JSON array of user IDs
        'usage_count' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'conditions' => 'array',      // JSON conditions
        'is_stackable' => 'boolean',
        'is_free_shipping' => 'boolean',
    ];

    // Use our custom factory
    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    // ============================================
    // SCOPES - Easy query filters
    // ============================================

    // Only active coupons within their date range
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    // Valid coupons (active + within dates + usage limits + user eligibility)
    public function scopeValid($query, ?int $userId = null): self
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhere('usage_count', '<', DB::raw('usage_limit'));
            });
    }

    // Expired coupons
    public function scopeExpired($query): self
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    // Coupons for a specific user (via user_ids whitelist)
    public function scopeForUser($query, int $userId): self
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_ids')
                ->orWhereJsonContains('user_ids', $userId);
        });
    }

    // Coupons valid for a specific currency
    public function scopeForCurrency($query, string $currency): self
    {
        return $query->where(function ($q) use ($currency) {
            $q->whereJsonDoesntContain('conditions->currencies', $currency)
                ->orWhereRaw('JSON_CONTAINS(conditions->"currencies", ?)', [strtoupper($currency)]);
        });
    }

    // ============================================
    // COUPON VALIDATION METHODS
    // ============================================

    /**
     * Check if coupon is valid for a given user, amount, and currency
     * Returns true/false - simple and clear
     */
    public function isValid(?int $userId = null, ?float $amount = null, ?string $currency = null): bool
    {
        // 1. Must be active
        if (! $this->is_active) {
            return false;
        }

        // 2. Check start date
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        // 3. Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // 4. Check usage limit
        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        // 4. Check per-user limit
        if ($userId !== null && $this->usage_limit_per_user) {
            $userUsage = $this->usages()->where('user_id', $userId)->count();
            if ($userUsage >= $this->usage_limit_per_user) {
                return false;
            }
        }

        // 5. Check user whitelist
        if ($userId !== null && $this->user_ids !== null) {
            if (!in_array($userId, $this->user_ids)) {
                return false;
            }
        }

        // 5. Check minimum order amount
        if ($amount !== null && $this->min_order_amount && $amount < $this->min_order_amount) {
            return false;
        }

        // 6. Check currency restrictions
        if ($currency && $this->conditions && isset($this->conditions['currencies'])) {
            if (!in_array(strtoupper($currency), array_map('strtoupper', $this->conditions['currencies']))) {
                return false;
            }
        }

        return true;
    }

    // Simple wrapper - can this coupon be applied?
    public function canApply(?int $userId = null, ?float $amount = null, ?string $currency = null): bool
    {
        return $this->isValid($userId, $amount, $currency);
    }

    // ============================================
    // CALCULATE DISCOUNT AMOUNT
    // ============================================

    /**
     * Calculate discount in cents for a given amount
     * Returns discount in cents
     */
    public function calculateDiscount(int $amountCents): int
    {
        $discount = 0;

        switch ($this->type) {
            case 'percentage':
                // Calculate percentage discount
                $discount = (int) round($amountCents * $this->value / 100);
                
                // Cap at max discount amount if set
                if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
                    $discount = $this->max_discount_amount;
                }
                break;

            case 'fixed':
                // Fixed amount off, but not more than the order
                $discount = min($this->value, $amountCents);
                break;

            case 'buy_x_get_y':
                // Handled at checkout level with quantity
                break;

            case 'tiered':
                // Handled at checkout level with quantity
                break;

            case 'free_shipping':
                // Free shipping handled separately (not a monetary discount)
                break;
        }

        // Cap at max discount amount if set
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }

        // Never discount more than the order amount
        return min($discount, $amountCents);
    }

    /**
     * Generate a random coupon code
     * @param string $prefix Optional prefix like 'SAVE' or 'WELCOME'
     * @return string Like "SAVE-ABC123XY"
     */
    public static function generateCode(string $prefix = ''): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No confusing chars
        $length = 8;
        $code = $prefix;
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return strtoupper($code);
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}