<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CheckoutSummaryRequest extends FormRequest
{
    private const SUPPORTED_PAYMENT_METHODS = [
        'cash_on_delivery',
        'manual_wallet_transfer',
        'manual_bank_transfer',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_method' => $this->trimmed('shipping_method'),
            'payment_method' => $this->nullableTrimmed('payment_method'),
        ]);
    }

    public function rules(): array
    {
        return [
            'shipping_method' => [
                'required',
                'string',
                Rule::exists('shipping_methods', 'code')->where('is_active', true),
            ],
            'payment_method' => [
                'nullable',
                'string',
                Rule::exists('payment_methods', 'code')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('code', self::SUPPORTED_PAYMENT_METHODS)
                ),
            ],
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        $field = $validator->errors()->has('shipping_method')
            ? 'shipping_method'
            : 'payment_method';
        $code = $field === 'shipping_method'
            ? 'shipping_method_unavailable'
            : 'payment_method_unavailable';

        throw new HttpResponseException(response()->json([
            'success' => false,
            'errors' => [[
                'code' => $code,
                'field' => $field,
                'message' => __('shop.checkout.failures.'.$code),
            ]],
        ], 422));
    }

    private function trimmed(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }

    private function nullableTrimmed(string $key): mixed
    {
        $value = $this->trimmed($key);

        return $value === '' ? null : $value;
    }
}
