<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ThrottlesLoginAttempts
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_LOGIN_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'The provided credentials are invalid.',
        ]);
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), self::LOGIN_DECAY_SECONDS);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        $email = Str::lower(trim((string) $this->input('email')));
        $identifier = hash('sha256', $email.'|'.($this->ip() ?? ''));

        return $this->loginLimiterNamespace().':'.$identifier;
    }

    abstract protected function loginLimiterNamespace(): string;
}
