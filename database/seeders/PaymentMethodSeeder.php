<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::updateOrCreate(
            ['code' => 'cash_on_delivery'],
            [
                'name' => 'Cash on Delivery',
                'is_active' => true,
                'requires_payment_before_processing' => false,
                'sort_order' => 1,
            ]
        );

        PaymentMethod::updateOrCreate(
            ['code' => 'online_card'],
            [
                'name' => 'Online Card',
                'is_active' => true,
                'requires_payment_before_processing' => true,
                'sort_order' => 2,
            ]
        );
    }
}
