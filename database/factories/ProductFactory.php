<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['configurable_id' => null, 'type' => ProductType::Simple->value, 'product_number' => null,
            'sku' => fake()->unique()->bothify('SKU-#####'), 'price' => 10, 'special_price' => null,
            'special_price_from' => null, 'special_price_to' => null, 'business_mode' => null,
            'is_new' => false, 'is_featured' => false, 'is_visible_individually' => true, 'status' => true];
    }
}
