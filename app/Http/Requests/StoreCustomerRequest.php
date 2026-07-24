<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreCustomerRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_if:has_account,1', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'has_account' => ['required', 'boolean'],
            'password' => [
                'nullable',
                'required_if:has_account,1',
                'prohibited_if:has_account,0',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'is_active' => ['required', 'boolean'],
            'account_type' => ['prohibited'],
        ];
    }
}
