<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::firstOrCreate(
            ['code' => 'cash_on_delivery'],
            [
                'name' => 'Cash on Delivery',
                'type' => PaymentMethodType::Offline,
                'is_active' => true,
                'requires_payment_before_processing' => false,
                'sort_order' => 1,
            ]
        );

        PaymentMethod::firstOrCreate(
            ['code' => 'manual_wallet_transfer'],
            [
                'name' => 'Manual Wallet Transfer',
                'type' => PaymentMethodType::ManualTransfer,
                'is_active' => true,
                'requires_payment_before_processing' => true,
                'sort_order' => 2,
            ]
        );

        PaymentMethod::firstOrCreate(
            ['code' => 'manual_bank_transfer'],
            [
                'name' => 'Manual Bank Transfer',
                'type' => PaymentMethodType::ManualTransfer,
                'is_active' => true,
                'requires_payment_before_processing' => true,
                'sort_order' => 3,
            ]
        );
    }
}
