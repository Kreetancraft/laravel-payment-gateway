<?php

namespace Kreetancraft\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kreetancraft\PaymentGateway\Database\Factories\PaymentFactory;
use Kreetancraft\PaymentGateway\Enums\PaymentStatus;
use Kreetancraft\PaymentGateway\Events\PaymentFailed;
use Kreetancraft\PaymentGateway\Events\PaymentSucceeded;

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
        'payable_type',
        'payable_id',
    ];

    /**
     * What this payment pays for — an invoice, a booking, whatever the host
     * sells. Nullable so an application that has not adopted the Payable
     * contract keeps working.
     */
    /**
     * The checkout alias for this payment's payable, or null.
     *
     * `payable_type` holds the morph class; checkout takes the alias from the
     * `payables` allowlist. Reversing the map is what lets a failed or cancelled
     * payment offer a retry link that still knows what was being bought —
     * without it the buyer is sent to a checkout with nothing in it.
     */
    public function payableAlias(): ?string
    {
        if (blank($this->payable_type)) {
            return null;
        }

        foreach ((array) config('payment-gateway.payables', []) as $alias => $class) {
            if ($this->payable_type === $class || $this->payable_type === $alias) {
                return (string) $alias;
            }
        }

        return null;
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

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

        // The seam for everything that happens because money arrived, or did
        // not. Fired here rather than from the webhook handler because four
        // different paths settle a payment — the webhook, a manual verify, the
        // reconcile sweep and the re-verify job — and all of them write the
        // status through the model.
        //
        // `wasChanged` is what makes it safe to generate an invoice from: it is
        // true only when the status actually moved, so the same webhook
        // arriving twice, or a sweep re-checking a settled payment, does not
        // fire it again.
        static::saved(function (Payment $payment): void {
            if (! $payment->wasChanged('status')) {
                return;
            }

            // `wasChanged` is not quite enough on its own. Re-saving the same
            // instance with the status it already holds still counts as a
            // change once the attribute has been touched, so compare the value
            // this save started from. Inside `saved` the original has not been
            // synced yet, so it is the previous status.
            if ($payment->getOriginal('status') === $payment->status) {
                return;
            }

            match ($payment->status) {
                PaymentStatus::Succeeded => PaymentSucceeded::dispatch($payment),
                PaymentStatus::Failed, PaymentStatus::Canceled => PaymentFailed::dispatch($payment),
                default => null,
            };
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
