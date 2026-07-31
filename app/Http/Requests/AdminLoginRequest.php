<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ThrottlesLoginAttempts;
use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
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

    protected function loginLimiterNamespace(): string
    {
        return 'admin-login';
    }
}
