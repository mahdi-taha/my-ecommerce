<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class ApplyCheckoutCouponRequest extends CheckoutSummaryRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $code = $this->input('coupon_code');
        $this->merge([
            'coupon_code' => is_string($code) ? trim($code) : $code,
        ]);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'coupon_code' => ['required', 'string', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        if (! $this->expectsJson()) {
            throw new ValidationException($validator);
        }

        $field = $validator->errors()->has('coupon_code')
            ? 'coupon_code'
            : ($validator->errors()->has('shipping_method') ? 'shipping_method' : 'payment_method');
        $code = match ($field) {
            'coupon_code' => 'coupon_invalid',
            'shipping_method' => 'shipping_method_unavailable',
            default => 'payment_method_unavailable',
        };

        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => [[
                'code' => $code,
                'field' => $field,
                'message' => $validator->errors()->first($field),
            ]],
        ], 422));
    }
}
