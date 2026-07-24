<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_datatable_distinguishes_variant_from_parent(): void
    {
        $admin = User::factory()->create();
        $parent = Product::factory()->create(['type' => 'configurable', 'sku' => 'PARENT']);
        $parent->translations()->create(['locale' => 'en', 'name' => 'Parent', 'url_key' => 'parent']);
        Product::factory()->create(['configurable_id' => $parent->id, 'sku' => 'PARENT-red', 'is_visible_individually' => false]);

        $this->actingAs($admin, 'admin')->withHeader('X-Requested-With', 'XMLHttpRequest')->getJson(route('admin.products.index', [
            'draw' => 1, 'start' => 0, 'length' => 10, 'type' => 'variant',
        ]))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.type', 'Variant');
    }
}
