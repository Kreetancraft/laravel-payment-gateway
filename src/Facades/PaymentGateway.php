<?php

namespace Kreetancraft\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;

class PaymentGateway extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'payment-gateway';
    }
}
