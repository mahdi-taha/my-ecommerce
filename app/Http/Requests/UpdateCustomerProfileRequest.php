<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'phone' => $phone === '' ? null : $phone,
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'account_type' => ['prohibited'],
            'has_account' => ['prohibited'],
            'is_active' => ['prohibited'],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
        ];
    }
}
