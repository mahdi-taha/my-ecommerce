<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ThrottlesLoginAttempts;
use Illuminate\Foundation\Http\FormRequest;

class CustomerLoginRequest extends FormRequest
{
    use ThrottlesLoginAttempts;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function loginFailureMessage(): string
    {
        return __('shop.auth.login.invalid_credentials');
    }

    protected function loginLimiterNamespace(): string
    {
        return 'customer-login';
    }
}
