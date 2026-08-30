<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Data;

use Spatie\LaravelData\Data;

class VerificationResult extends Data
{
    public function __construct(
        public bool $success,
        public string $transactionId,
        public string $status,
        public float $amount,
        public string $currency,
        public ?string $paidAt = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(string $transactionId, string $status, float $amount, string $currency, ?string $paidAt = null): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            status: $status,
            amount: $amount,
            currency: $currency,
            paidAt: $paidAt,
        );
    }

    public static function failure(string $transactionId, string $errorMessage): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            status: 'failed',
            amount: 0,
            currency: '',
            errorMessage: $errorMessage,
        );
    }
}
