<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Database\Factories\PaymentFactory;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property int|null $user_id
 * @property int $amount_cents
 * @property string $currency
 * @property string $gateway
 * @property string|null $gateway_reference
 * @property string $status
 * @property int $refunded_amount_cents
 * @property string $idempotency_key
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property string|null $customer_email
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string|null $customer_address
 * @property string|null $description
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'reference',
        'user_id',
        'amount_cents',
        'currency',
        'gateway',
        'gateway_reference',
        'status',
        'refunded_amount_cents',
        'idempotency_key',
        'paid_at',
        'refunded_at',
        'customer_email',
        'customer_name',
        'customer_phone',
        'customer_address',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
            'status' => PaymentStatus::class,
        ];
    }

    protected $attributes = [
        'status' => 'pending',
        'refunded_amount_cents' => 0,
    ];

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if (blank($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }

            if (blank($payment->reference)) {
                $date = now()->format('ymd');
                $rand = Str::upper(Str::random(6));
                $payment->reference = "PMT-{$date}-{$rand}";
            }

            if (blank($payment->idempotency_key)) {
                $rand = Str::random(16);
                $payment->idempotency_key = hash('sha256', "{$payment->gateway}:{$payment->amount_cents}:{$payment->currency}:{$rand}");
            }
        });
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeSucceeded(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Succeeded);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Pending);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeForGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    public function netAmountCents(): Attribute
    {
        return Attribute::get(fn (): int => max(0, $this->amount_cents - $this->refunded_amount_cents));
    }

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }

    public function isRefunded(): bool
    {
        return $this->refunded_amount_cents > 0;
    }

    public function isFullyRefunded(): bool
    {
        return $this->refunded_amount_cents >= $this->amount_cents;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'), 'user_id');
    }
}
