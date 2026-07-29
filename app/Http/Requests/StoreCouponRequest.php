<?php

namespace App\Http\Requests;

use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'code', 'name', 'type', 'value', 'starts_at', 'ends_at',
            'minimum_subtotal', 'usage_limit', 'per_customer_usage_limit',
        ] as $field) {
            $value = $this->input($field);
            $value = is_string($value) ? trim($value) : $value;
            $normalized[$field] = $value === '' ? null : $value;
        }

        $normalized['code'] = mb_strtoupper((string) ($normalized['code'] ?? ''), 'UTF-8');
        $normalized['is_active'] = $this->boolean('is_active');
        $normalized['is_first_order_only'] = $this->boolean('is_first_order_only');

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'code' => $this->codeRules(),
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required',
                'decimal:0,4',
                'gt:0',
                Rule::when($this->input('type') === CouponType::Percentage->value, ['lte:100']),
            ],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'minimum_subtotal' => ['nullable', 'decimal:0,4', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_first_order_only' => ['required', 'boolean'],
        ];
    }

    protected function codeRules(): array
    {
        return [
            'required',
            'string',
            'max:100',
            'regex:/^[A-Z0-9_-]+$/',
            Rule::unique('coupons', 'code'),
        ];
    }
}
