<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone'));
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => trim((string) $this->input('email')),
            'phone' => $phone === '' ? null : $phone,
        ]);
    }

    public function rules(): array
    {
        $user = $this->user('customer');

        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'account_type' => ['prohibited'],
            'has_account' => ['prohibited'],
            'is_active' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }
}
