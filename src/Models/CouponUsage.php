<?php

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Kreetancraft\PaymentGateway\Database\Factories\CouponUsageFactory;

/**
 * @property int $id
 * @property int $coupon_id
 * @property int|null $user_id
 * @property string $order_type
 * @property int $order_id
 * @property int $usage_count
 * @property int $amount_discounted_cents
 * @property string $currency
 * @property array|null $metadata
 */
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coupon_id' => 'integer',
            'user_id' => 'integer',
            'order_id' => 'integer',
            'usage_count' => 'integer',
            'amount_discounted_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function newFactory(): CouponUsageFactory
    {
        return CouponUsageFactory::new();
    }

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

    /**
     * @param  Builder<CouponUsage>  $query
     * @return Builder<CouponUsage>
     */
    public function scopeForCoupon(Builder $query, int $couponId): Builder
    {
        return $query->where('coupon_id', $couponId);
    }

    /**
     * @param  Builder<CouponUsage>  $query
     * @return Builder<CouponUsage>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<CouponUsage>  $query
     * @return Builder<CouponUsage>
     */
    public function scopeForOrder(Builder $query, string $orderType, int $orderId): Builder
    {
        return $query->where('order_type', $orderType)->where('order_id', $orderId);
    }

    /**
     * @param  Builder<CouponUsage>  $query
     * @return Builder<CouponUsage>
     */
    public function scopeRecent(Builder $query): Builder
    {
        return $query->latest('created_at');
    }

    public function getFormattedAmountDiscounted(): string
    {
        return number_format($this->amount_discounted_cents / 100, 2);
    }

    public function getFormattedCurrency(): string
    {
        return strtoupper($this->currency);
    }

    /**
     * Static helper to record coupon usage.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function recordUsage(
        int $couponId,
        int $userId,
        string $orderType,
        int $orderId,
        int $amountDiscountedCents,
        string $currency,
        array $metadata = []
    ): self {
        // One coupon, one order, one row. This was a bare `create()`, so a
        // double-clicked apply, a retried job or a redelivered webhook recorded
        // the redemption again — and each duplicate counted afresh against the
        // coupon's usage cap, so a coupon limited to 100 could be exhausted by
        // 50 customers.
        //
        // A repeat is the same redemption arriving twice, so the existing row is
        // returned untouched rather than its discount being added again.
        $existing = static::query()
            ->where('coupon_id', $couponId)
            ->where('order_type', $orderType)
            ->where('order_id', $orderId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

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
}
