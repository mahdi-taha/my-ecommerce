<?php

namespace Database\Seeders;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'inside_beirut', 'name' => 'Inside Beirut', 'type' => ShippingMethodType::Delivery, 'sort_order' => 1],
            ['code' => 'outside_beirut', 'name' => 'Outside Beirut', 'type' => ShippingMethodType::Delivery, 'sort_order' => 2],
            ['code' => 'south_lebanon', 'name' => 'South Lebanon', 'type' => ShippingMethodType::Delivery, 'sort_order' => 3],
            ['code' => 'store_pickup', 'name' => 'Store Pickup', 'type' => ShippingMethodType::Pickup, 'sort_order' => 4],
        ];

        foreach ($methods as $method) {
            ShippingMethod::firstOrCreate(
                ['code' => $method['code']],
                array_merge($method, [
                    'amount' => '0.0000',
                    'description' => null,
                    'is_active' => false,
                ])
            );
        }
    }
}
