<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedInput());
    }

    public function rules(): array
    {
        return $this->addressRules();
    }

    protected function normalizedInput(): array
    {
        $values = [];

        foreach ([
            'label', 'first_name', 'last_name', 'company', 'phone', 'state',
            'city', 'address_line_1', 'address_line_2', 'postal_code',
        ] as $field) {
            $value = trim((string) $this->input($field));
            $values[$field] = $value === '' ? null : $value;
        }

        $values['country_code'] = strtoupper(trim((string) $this->input('country_code')));
        $values['is_default_shipping'] = $this->boolean('is_default_shipping');
        $values['is_default_billing'] = $this->boolean('is_default_billing');

        return $values;
    }

    protected function addressRules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'alpha', 'size:2'],
            'state' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'is_default_shipping' => ['required', 'boolean'],
            'is_default_billing' => ['required', 'boolean'],
            'user_id' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'state' => __('shop.account.addresses.fields.governorate'),
        ];
    }
}
