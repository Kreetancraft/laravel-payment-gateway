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
}
