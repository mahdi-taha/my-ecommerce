<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerForgotPasswordRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('shop.auth.password.generic_response'),
            ]);
        }

        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function throttleKey(): string
    {
        $identifier = hash('sha256', $this->string('email')->toString().'|'.($this->ip() ?? ''));

        return 'customer-password-email:'.$identifier;
    }
}
