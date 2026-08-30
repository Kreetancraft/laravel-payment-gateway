<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Data;

use Spatie\LaravelData\Data;

class WebhookResult extends Data
{
    public function __construct(
        public bool $success,
        public string $eventType,
        public string $transactionId,
        public string $status,
        public float $amount,
        public string $currency,
        public ?string $errorMessage = null,
    ) {}

    public static function success(string $eventType, string $transactionId, string $status, float $amount, string $currency): self
    {
        return new self(
            success: true,
            eventType: $eventType,
            transactionId: $transactionId,
            status: $status,
            amount: $amount,
            currency: $currency,
        );
    }

    public static function failure(string $eventType, string $transactionId, string $errorMessage): self
    {
        return new self(
            success: false,
            eventType: $eventType,
            transactionId: $transactionId,
            status: 'failed',
            amount: 0,
            currency: '',
            errorMessage: $errorMessage,
        );
    }
}
