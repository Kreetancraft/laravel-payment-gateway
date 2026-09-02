<?php

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount_amount',
        'min_order_amount',
        'usage_limit',
        'usage_limit_per_user',
        'user_ids',
        'usage_count',
        'starts_at',
        'expires_at',
        'is_active',
        'conditions',
        'is_stackable',
        'is_free_shipping',
    ];

    // Automatic casting for proper types
    protected function casts(): array
    {
        return [
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
    }

    // Use our custom factory
    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Coupon $coupon): void {
            if (blank($coupon->uuid)) {
                $coupon->uuid = (string) Str::uuid();
            }
        });
    }

    // Only active coupons within their date range
    public function scopeActive(Builder $query): Builder
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
    public function scopeValid(Builder $query, ?int $userId = null): Builder
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhere('usage_count', '<', DB::raw('usage_limit'));
            });
    }

    // Expired coupons
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    // Coupons for a specific user (via user_ids whitelist)
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_ids')
                ->orWhereJsonContains('user_ids', $userId);
        });
    }

    // Coupons valid for a specific currency
    public function scopeForCurrency(Builder $query, string $currency): Builder
    {
        return $query->where(function ($q) use ($currency) {
            $q->whereJsonDoesntContain('conditions->currencies', $currency)
                ->orWhereRaw('JSON_CONTAINS(conditions->"currencies", ?)', [strtoupper($currency)]);
        });
    }

    public function isValid(?int $userId = null, ?float $amount = null, ?string $currency = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        if ($userId !== null && $this->usage_limit_per_user) {
            $userUsage = $this->usages()->where('user_id', $userId)->count();
            if ($userUsage >= $this->usage_limit_per_user) {
                return false;
            }
        }

        if ($userId !== null && $this->user_ids !== null) {
            if (! in_array($userId, $this->user_ids)) {
                return false;
            }
        }

        if ($amount !== null && $this->min_order_amount && $amount < $this->min_order_amount) {
            return false;
        }

        if (! $this->supportsCurrency($currency)) {
            return false;
        }

        return true;
    }

    public function canApply(?int $userId = null, ?float $amount = null, ?string $currency = null): bool
    {
        return $this->isValid($userId, $amount, $currency);
    }

    public function supportsCurrency(?string $currency): bool
    {
        if (blank($currency)) {
            return true;
        }

        $currencies = $this->conditions['currencies'] ?? null;

        if (empty($currencies)) {
            return true;
        }

        return in_array(strtoupper($currency), array_map('strtoupper', (array) $currencies), true);
    }

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
     *
     * @param  string  $prefix  Optional prefix like 'SAVE' or 'WELCOME'
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

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
