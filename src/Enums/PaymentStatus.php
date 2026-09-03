<?php

namespace Kreetancraft\PaymentGateway\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';
    case RequiresAction = 'requires_action';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Succeeded => __('Succeeded'),
            self::Failed => __('Failed'),
            self::Canceled => __('Canceled'),
            self::Refunded => __('Refunded'),
            self::PartiallyRefunded => __('Partially Refunded'),
            self::RequiresAction => __('Requires Action'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Succeeded => 'green',
            self::Failed => 'red',
            self::Canceled => 'zinc',
            self::Refunded => 'amber',
            self::PartiallyRefunded => 'amber',
            self::RequiresAction => 'blue',
        };
    }

    public function isSucceeded(): bool
    {
        return $this === self::Succeeded;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    /**
     * May a payment in this status move to that one?
     *
     * Money only travels one way. A payment that has settled, failed or been
     * cancelled has reached an answer, and a later message repeating an older
     * one must not undo it — that is how a redelivered webhook turned a refunded
     * payment back into a succeeded one and charged fulfilment a second time.
     *
     * Repeating the status a payment already holds is allowed and simply changes
     * nothing, so a duplicate delivery is a no-op rather than an error.
     */
    public function canMoveTo(self $target): bool
    {
        if ($this === $target) {
            // Nothing moves, so nothing can go wrong. PartiallyRefunded is the
            // one that genuinely repeats: each refund writes it again.
            return $this === self::PartiallyRefunded;
        }

        return in_array($target, $this->allowedNext(), true);
    }

    /**
     * @return list<self>
     */
    private function allowedNext(): array
    {
        return match ($this) {
            // Still in flight: any first answer is acceptable.
            self::Pending => [self::Succeeded, self::Failed, self::Canceled, self::RequiresAction],

            // Waiting on the buyer to finish authenticating.
            self::RequiresAction => [self::Succeeded, self::Failed, self::Canceled],

            // Paid. The only way onward is giving the money back.
            self::Succeeded => [self::Refunded, self::PartiallyRefunded],
            self::PartiallyRefunded => [self::Refunded],

            // Final.
            self::Failed, self::Canceled, self::Refunded => [],
        };
    }
}
