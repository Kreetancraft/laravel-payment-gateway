<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Kreetancraft\PaymentGateway\Database\Factories\CouponUsageFactory;

class CouponUsage extends Model
{
    use HasFactory;

    protected $table = 'coupon_usages';

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_type',
        'order_id',
        'usage_count',
        'amount_discounted_cents',
        'currency',
        'metadata',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'amount_discounted_cents' => 'integer',
        'metadata' => 'array',
    ];

    protected static function newFactory(): CouponUsageFactory
    {
        return CouponUsageFactory::new();
    }

    // Relationships
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'user_id');
    }

    public function order(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeForCoupon($query, int $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrder($query, string $orderType, int $orderId)
    {
        return $query->where('order_type', $orderType)->where('order_id', $orderId);
    }

    public function scopeRecent($query)
    {
        return $query->latest('created_at');
    }

    // Helper attributes
    public function getAmountDiscountedAttribute(): int
    {
        return $this->amount_discounted_cents;
    }

    public function getFormattedAmountDiscountedAttribute(): string
    {
        return number_format($this->amount_discounted_cents / 100, 2);
    }

    public function getFormattedCurrencyAttribute(): string
    {
        return strtoupper($this->currency);
    }

    // Static helper to record coupon usage
    public static function recordUsage(
        int $couponId,
        int $userId,
        string $orderType,
        int $orderId,
        int $amountDiscountedCents,
        string $currency,
        array $metadata = []
    ): self
    {
        return static::create([
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'order_type' => $orderType,
            'order_id' => $orderId,
            'usage_count' => 1,
            'amount_discounted_cents' => $amountDiscountedCents,
            'currency' => strtoupper($currency),
            'metadata' => $metadata,
        ]);
    }

    // Accessor methods
    public function getAmountDiscountedAttribute(): int
    {
        return $this->amount_discounted_cents;
    }

    public function getFormattedAmountDiscountedAttribute(): string
    {
        return number_format($this->amount_discounted_cents / 100, 2);
    }

    public function getFormattedCurrencyAttribute(): string
    {
        return strtoupper($this->currency);
    }
}