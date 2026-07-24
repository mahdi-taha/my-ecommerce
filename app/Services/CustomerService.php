<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $hasAccount = (bool) $data['has_account'];
            $email = $this->normalizeEmail($data['email'] ?? null);

            return User::create([
                'name' => trim((string) $data['name']),
                'first_name' => trim((string) $data['first_name']),
                'last_name' => trim((string) $data['last_name']),
                'email' => $email,
                'phone' => $this->normalizePhone($data['phone'] ?? null),
                'password' => $hasAccount ? $data['password'] : null,
                'account_type' => AccountType::Customer->value,
                'has_account' => $hasAccount,
                'is_active' => (bool) $data['is_active'],
                'email_verified_at' => $email === null ? null : now(),
            ]);
        });
    }

    public function update(User $customer, array $data): User
    {
        return DB::transaction(function () use ($customer, $data) {
            $this->ensureCustomer($customer);
            $customer->update([
                'name' => trim((string) $data['name']),
                'first_name' => trim((string) $data['first_name']),
                'last_name' => trim((string) $data['last_name']),
                'email' => $this->normalizeEmail($data['email'] ?? null),
                'phone' => $this->normalizePhone($data['phone'] ?? null),
                'is_active' => (bool) $data['is_active'],
            ]);

            return $customer->fresh();
        });
    }

    public function updatePassword(User $customer, string $password): User
    {
        return DB::transaction(function () use ($customer, $password) {
            $this->ensureCustomer($customer);
            $this->ensureAccountEnabled($customer);
            $customer->update([
                'password' => $password,
            ]);

            return $customer->fresh();
        });
    }

    public function updateStatus(User $customer, bool $isActive): User
    {
        return DB::transaction(function () use ($customer, $isActive) {
            $this->ensureCustomer($customer);
            $customer->update([
                'is_active' => $isActive,
            ]);

            return $customer->fresh();
        });
    }

    public function updateProfile(User $customer, array $data): User
    {
        return DB::transaction(function () use ($customer, $data) {
            $this->ensureCustomer($customer);
            $this->ensureAccountEnabled($customer);
            $customer->update([
                'name' => trim((string) $data['name']),
                'first_name' => trim((string) $data['first_name']),
                'last_name' => trim((string) $data['last_name']),
                'email' => $this->normalizeEmail($data['email'] ?? null),
                'phone' => $this->normalizePhone($data['phone'] ?? null),
            ]);

            return $customer->fresh();
        });
    }

    public function updateOwnPassword(User $customer, string $password): User
    {
        return $this->updatePassword($customer, $password);
    }

    private function ensureCustomer(User $customer): void
    {
        if ($customer->account_type !== AccountType::Customer) {
            throw ValidationException::withMessages([
                'customer' => 'The selected user is not a customer.',
            ]);
        }
    }

    private function ensureAccountEnabled(User $customer): void
    {
        if (! $customer->has_account) {
            throw ValidationException::withMessages([
                'customer' => 'The customer does not have an enabled account.',
            ]);
        }
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim((string) $email);

        return $email === '' ? null : $email;
    }

    private function normalizePhone(mixed $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim((string) $phone);

        return $phone === '' ? null : $phone;
    }
}
