<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_option_accepts_active_simple_item_and_preserves_translations(): void
    {
        $bundle = Product::factory()->create(['type' => 'bundle', 'price' => 0]);
        $simple = Product::factory()->create(['type' => 'simple', 'status' => true]);
        $service = app(ProductService::class);
        $option = $service->createBundleOption($bundle, ['title_en' => 'Choose', 'title_ar' => 'اختر',
            'type' => 'select', 'is_required' => true, 'sort_order' => 0, 'min_selections' => null, 'max_selections' => null]);
        $service->createBundleOptionItem($option, ['product_id' => $simple->id, 'default_quantity' => 1,
            'is_default' => true, 'sort_order' => 0, 'price_override' => null]);

        $this->assertCount(2, $option->translations);
        $this->assertDatabaseHas('bundle_option_items', ['bundle_option_id' => $option->id, 'product_id' => $simple->id]);
        $this->assertNull($bundle->inventory);
    }
}
