<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'first_name' => 'Test',
                'last_name' => 'User',
                'phone' => null,
                'password' => 'password',
                'account_type' => AccountType::Admin->value,
                'has_account' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
