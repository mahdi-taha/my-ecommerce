<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class GuestCartTokenService
{
    public const COOKIE_NAME = 'storefront_cart';

    public function fromRequest(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE_NAME);

        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1
            ? $token
            : null;
    }

    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function cookie(string $token, int $lifetimeDays): Cookie
    {
        return cookie(
            self::COOKIE_NAME,
            $token,
            $lifetimeDays * 24 * 60,
            '/',
            null,
            config('session.secure'),
            true,
            false,
            'lax'
        );
    }

    public function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE_NAME);
    }
}
