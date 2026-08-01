<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerRegistrationRequest extends FormRequest
{
    private const MAX_REGISTRATION_ATTEMPTS = 5;

    private const REGISTRATION_DECAY_SECONDS = 60;

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
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => $phone === '' ? null : $phone,
        ]);

        $this->ensureIsNotRateLimited();
        RateLimiter::hit($this->throttleKey(), self::REGISTRATION_DECAY_SECONDS);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
            ],
            'account_type' => ['prohibited'],
            'has_account' => ['prohibited'],
            'is_active' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
            'last_login_at' => ['prohibited'],
        ];
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        $identifier = hash('sha256', $this->string('email')->toString().'|'.($this->ip() ?? ''));

        return 'customer-register:'.$identifier;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_REGISTRATION_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('shop.auth.register.rate_limited'),
        ]);
    }
}
