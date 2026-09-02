<?php

namespace Kreetancraft\PaymentGateway\Data;

use Spatie\LaravelData\Data;

class RefundResult extends Data
{
    public function __construct(
        public bool $success,
        public string $transactionId,
        public float $amount,
        public ?string $refundId = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {}

    public static function success(string $transactionId, float $amount, ?string $refundId = null): self
    {
        return new self(
            success: true,
            transactionId: $transactionId,
            amount: $amount,
            refundId: $refundId,
        );
    }

    public static function failure(string $transactionId, float $amount, string $errorMessage, ?string $errorCode = null): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            amount: $amount,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
        );
    }
}
