<?php

namespace Database\Factories;

use App\Enums\BundleOptionType;
use App\Enums\ProductType;
use App\Models\BundleOption;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BundleOption> */
class BundleOptionFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory()->state(['type' => ProductType::Bundle->value]),
            'type' => BundleOptionType::Select->value, 'is_required' => true, 'sort_order' => 0,
            'min_selections' => 1, 'max_selections' => 1];
    }
}
