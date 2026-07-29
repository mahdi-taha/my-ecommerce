<?php

namespace Database\Factories;

use App\Enums\CouponType;
use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE-####')),
            'name' => fake()->words(3, true),
            'type' => CouponType::Fixed,
            'value' => '10.0000',
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => null,
            'usage_limit' => null,
            'per_customer_usage_limit' => null,
            'is_first_order_only' => false,
        ];
    }
}
