<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
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
        $normalized = [
            'shipping_method' => $this->trimmed('shipping_method'),
            'payment_method' => $this->trimmed('payment_method'),
            'address_source' => $this->trimmed('address_source'),
            'manual_address' => $this->normalizedAddress('manual_address'),
            'customer' => $this->normalizedCustomer(),
        ];

        foreach (['saved_address_id', 'save_address', 'make_default_shipping', 'make_default_billing'] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $normalized[$field] = $field === 'saved_address_id'
                ? $this->input($field)
                : $this->boolean($field);
        }

        $this->merge($normalized);
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
                Rule::exists('payment_methods', 'code')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('type', [
                            PaymentMethodType::Offline->value,
                            PaymentMethodType::ManualTransfer->value,
                        ])
                ),
            ],
            'customer' => ['required', 'array'],
            'customer.first_name' => ['required', 'string', 'max:255'],
            'customer.last_name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.user_id' => ['prohibited'],
            'customer.account_type' => ['prohibited'],
            'address_source' => ['required', Rule::in(['saved', 'manual'])],
            'saved_address_id' => [
                Rule::requiredIf(fn () => $this->input('address_source') === 'saved'),
                Rule::prohibitedIf(fn () => $this->input('address_source') !== 'saved' || ! $this->customer()),
                'integer',
                Rule::exists('customer_addresses', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->customer()?->getKey() ?? 0)
                ),
            ],
            'manual_address' => [
                Rule::requiredIf(fn () => $this->input('address_source') === 'manual'),
                Rule::prohibitedIf(fn () => $this->input('address_source') !== 'manual'),
                'array',
            ],
            ...$this->addressRules('manual_address'),
            'save_address' => [
                Rule::prohibitedIf(fn () => ! $this->authenticatedManualFlow()),
                'boolean',
            ],
            'make_default_shipping' => [
                Rule::prohibitedIf(fn () => ! $this->savingManualAddress()),
                'boolean',
            ],
            'make_default_billing' => [
                Rule::prohibitedIf(fn () => ! $this->savingManualAddress()),
                'boolean',
            ],
        ];
    }

    private function addressRules(string $prefix): array
    {
        $requiredForManual = Rule::requiredIf(
            fn () => $this->input('address_source') === 'manual'
        );
        $requiredWhenSaving = Rule::requiredIf(fn () => $this->savingManualAddress());

        return [
            "{$prefix}.first_name" => [$requiredForManual, 'string', 'max:255'],
            "{$prefix}.last_name" => [$requiredForManual, 'string', 'max:255'],
            "{$prefix}.company" => ['nullable', 'string', 'max:255'],
            "{$prefix}.email" => ['nullable', 'email', 'max:255'],
            "{$prefix}.phone" => [$requiredWhenSaving, 'nullable', 'string', 'max:255'],
            "{$prefix}.address_line_1" => [$requiredForManual, 'string', 'max:255'],
            "{$prefix}.address_line_2" => ['nullable', 'string', 'max:255'],
            "{$prefix}.city" => [$requiredForManual, 'string', 'max:255'],
            "{$prefix}.state" => [$requiredWhenSaving, 'nullable', 'string', 'max:255'],
            "{$prefix}.postal_code" => ['nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => [$requiredForManual, 'string', 'size:2', 'alpha'],
            "{$prefix}.label" => ['nullable', 'string', 'max:255'],
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
            'label',
        ] as $field) {
            $normalized[$field] = in_array($field, ['company', 'email', 'phone', 'address_line_2', 'state', 'postal_code'], true)
                ? $this->nullableTrimmedValue($address[$field] ?? null)
                : $this->trimmedValue($address[$field] ?? null);
        }

        if (is_string($normalized['country_code'])) {
            $normalized['country_code'] = strtoupper($normalized['country_code']);
        }

        return collect($normalized)->contains(
            fn ($value) => $value !== null && $value !== ''
        ) ? $normalized : [];
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

    private function customer(): mixed
    {
        return $this->user('customer');
    }

    private function authenticatedManualFlow(): bool
    {
        return $this->customer() !== null && $this->input('address_source') === 'manual';
    }

    private function savingManualAddress(): bool
    {
        return $this->authenticatedManualFlow() && $this->boolean('save_address');
    }
}
