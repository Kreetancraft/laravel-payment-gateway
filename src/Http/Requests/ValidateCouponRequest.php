<?php

namespace Kreetancraft\PaymentGateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Kreetancraft\PaymentGateway\Rules\ValidCouponCode;

class ValidateCouponRequest extends FormRequest
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
            'amount_cents' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
