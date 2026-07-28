<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'shipping_method' => $this->trimmed('shipping_method'),
            'payment_method' => $this->trimmed('payment_method'),
            'customer' => $this->normalizedCustomer(),
            'billing_address' => $this->normalizedAddress('billing_address'),
            'shipping_address' => $this->normalizedAddress('shipping_address'),
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
                'required',
                'string',
                Rule::exists('payment_methods', 'code')->where('is_active', true),
            ],
            'customer' => ['required', 'array'],
            'customer.first_name' => ['required', 'string', 'max:255'],
            'customer.last_name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.user_id' => ['prohibited'],
            'customer.account_type' => ['prohibited'],
            'billing_address' => ['required', 'array'],
            'shipping_address' => ['required', 'array'],
            ...$this->addressRules('billing_address'),
            ...$this->addressRules('shipping_address'),
        ];
    }

    private function addressRules(string $prefix): array
    {
        return [
            "{$prefix}.first_name" => ['required', 'string', 'max:255'],
            "{$prefix}.last_name" => ['required', 'string', 'max:255'],
            "{$prefix}.company" => ['nullable', 'string', 'max:255'],
            "{$prefix}.email" => ['nullable', 'email', 'max:255'],
            "{$prefix}.phone" => ['nullable', 'string', 'max:255'],
            "{$prefix}.address_line_1" => ['required', 'string', 'max:255'],
            "{$prefix}.address_line_2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.city" => ['required', 'string', 'max:255'],
            "{$prefix}.state" => ['nullable', 'string', 'max:255'],
            "{$prefix}.postal_code" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => ['required', 'string', 'size:2', 'alpha'],
        ];
    }

    private function normalizedCustomer(): array
    {
        $customer = $this->input('customer');

        if (! is_array($customer)) {
            return [];
        }

        return [
            'first_name' => $this->trimmedValue($customer['first_name'] ?? null),
            'last_name' => $this->trimmedValue($customer['last_name'] ?? null),
            'phone' => $this->trimmedValue($customer['phone'] ?? null),
            'email' => $this->nullableTrimmedValue($customer['email'] ?? null),
            ...array_intersect_key($customer, array_flip(['user_id', 'account_type'])),
        ];
    }

    private function normalizedAddress(string $key): array
    {
        $address = $this->input($key);

        if (! is_array($address)) {
            return [];
        }

        $normalized = [];

        foreach ([
            'first_name',
            'last_name',
            'company',
            'email',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country_code',
        ] as $field) {
            $normalized[$field] = in_array($field, ['company', 'email', 'phone', 'address_line_2', 'state', 'postal_code'], true)
                ? $this->nullableTrimmedValue($address[$field] ?? null)
                : $this->trimmedValue($address[$field] ?? null);
        }

        if (is_string($normalized['country_code'])) {
            $normalized['country_code'] = strtoupper($normalized['country_code']);
        }

        return $normalized;
    }

    private function trimmed(string $key): mixed
    {
        return $this->trimmedValue($this->input($key));
    }

    private function trimmedValue(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function nullableTrimmedValue(mixed $value): mixed
    {
        $value = $this->trimmedValue($value);

        return $value === '' ? null : $value;
    }
}
