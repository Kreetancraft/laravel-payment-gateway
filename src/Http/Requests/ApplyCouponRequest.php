<?php

namespace Kreetancraft\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kreetancraft\PaymentGateway\Rules\ValidCouponCode;

class ApplyCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', new ValidCouponCode],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}
