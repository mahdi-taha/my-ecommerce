<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class CustomerPasswordResetService
{
    public function sendResetLink(string $email, string $locale): void
    {
        $customer = User::query()
            ->eligibleForPasswordReset()
            ->where('email', $email)
            ->first();

        if (! $customer) {
            return;
        }

        try {
            $broker = Password::broker('customers');
            $customer->sendCustomerPasswordResetNotification(
                $broker->createToken($customer),
                $locale
            );
        } catch (Throwable $exception) {
            Log::error('Customer password reset notification failed.', [
                'exception' => $exception,
            ]);
        }
    }

    public function reset(string $email, string $token, string $password): bool
    {
        $customer = User::query()
            ->eligibleForPasswordReset()
            ->where('email', $email)
            ->first();

        $broker = Password::broker('customers');

        if (! $customer || ! $broker->tokenExists($customer, $token)) {
            return false;
        }

        $customer->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $broker->deleteToken($customer);
        event(new PasswordReset($customer));

        return true;
    }
}
