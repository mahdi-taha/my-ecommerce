<?php

namespace Database\Factories;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShippingMethod> */
class ShippingMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'type' => ShippingMethodType::Delivery,
            'amount' => fake()->randomElement(['0.0000', '2.0000', '4.0000']),
            'description' => null,
            'is_active' => false,
            'sort_order' => 0,
        ];
    }
}
