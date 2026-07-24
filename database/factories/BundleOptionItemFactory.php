<?php

namespace Database\Factories;

use App\Models\BundleOption;
use App\Models\BundleOptionItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BundleOptionItem> */
class BundleOptionItemFactory extends Factory
{
    public function definition(): array
    {
        return ['bundle_option_id' => BundleOption::factory(), 'product_id' => Product::factory(),
            'default_quantity' => 1, 'is_default' => false, 'sort_order' => 0, 'price_override' => null];
    }
}
