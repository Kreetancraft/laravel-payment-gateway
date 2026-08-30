<?php

declare(strict_types=1);

namespace Kreetancraft\PaymentGateway\Data;

use Spatie\LaravelData\Data;

class PaymentResult extends Data
{
    public function __construct(
        public bool $success,
        public string $orderReference,
        public ?string $redirectUrl = null,
        public ?string $checkoutData = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {}

    public static function success(string $orderReference, ?string $redirectUrl = null, ?string $checkoutData = null): self
    {
        return new self(
            success: true,
            orderReference: $orderReference,
            redirectUrl: $redirectUrl,
            checkoutData: $checkoutData,
        );
    }

    public static function failure(string $orderReference, string $errorMessage, ?string $errorCode = null): self
    {
        return new self(
            success: false,
            orderReference: $orderReference,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
        );
    }
}
