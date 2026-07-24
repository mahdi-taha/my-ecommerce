<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $this->filled('email') ? trim((string) $this->input('email')) : null,
            'phone' => $phone === null || trim((string) $phone) === ''
                ? null
                : trim((string) $phone),
        ]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                Rule::requiredIf((bool) $customer?->has_account),
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($customer?->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_active' => ['required', 'boolean'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'account_type' => ['prohibited'],
            'has_account' => ['prohibited'],
        ];
    }
}
