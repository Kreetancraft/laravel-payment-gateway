<?php

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

        /**
         * Has the money actually moved?
         *
         * A successful `charge()` means the gateway accepted the request, not
         * that the buyer paid. Both gateways here hand the buyer off to a hosted
         * page and settle later over a webhook, so this stays false and the
         * payment stays pending until something authoritative says otherwise.
         * Only a gateway that takes the money synchronously should set it.
         */
        public bool $settled = false,
    ) {}

    public static function success(
        string $orderReference,
        ?string $redirectUrl = null,
        ?string $checkoutData = null,
        bool $settled = false,
    ): self {
        return new self(
            success: true,
            orderReference: $orderReference,
            redirectUrl: $redirectUrl,
            checkoutData: $checkoutData,
            settled: $settled,
        );
    }

    public static function failure(string $orderReference, string $errorMessage, string|int|null $errorCode = null): self
    {
        return new self(
            success: false,
            orderReference: $orderReference,
            errorMessage: $errorMessage,
            errorCode: $errorCode !== null ? (string) $errorCode : null,
        );
    }
}
