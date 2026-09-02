<?php

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
        public ?string $reference = null,
    ) {}

    public static function success(
        string $transactionId,
        string $status,
        float $amount,
        string $currency,
        ?string $paidAt = null,
        ?string $reference = null,
    ): self {
        return new self(
            success: true,
            transactionId: $transactionId,
            status: $status,
            amount: $amount,
            currency: $currency,
            paidAt: $paidAt,
            reference: $reference ?? $transactionId,
        );
    }

    /**
     * The gateway could not be asked, or has not decided yet.
     *
     * Distinct from failure() on purpose. A timeout, a TLS error or a status the
     * bank has not settled must leave the payment where it is — writing `failed`
     * over a captured payment because the network blinked is not recoverable,
     * and there is no way back from it without a manual correction.
     */
    public static function undetermined(string $transactionId, string $errorMessage, ?string $reference = null): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            status: 'pending',
            amount: 0,
            currency: '',
            errorMessage: $errorMessage,
            reference: $reference ?? $transactionId,
        );
    }

    public static function failure(string $transactionId, string $errorMessage, ?string $reference = null): self
    {
        return new self(
            success: false,
            transactionId: $transactionId,
            status: 'failed',
            amount: 0,
            currency: '',
            errorMessage: $errorMessage,
            reference: $reference ?? $transactionId,
        );
    }
}
