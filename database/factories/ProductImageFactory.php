<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductImage> */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'path' => 'products/images/'.fake()->uuid().'.jpg', 'is_base' => false, 'sort_order' => 0];
    }
}
