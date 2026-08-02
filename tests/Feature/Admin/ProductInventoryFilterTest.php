<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductInventoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_flags_combine_with_existing_status_filter(): void
    {
        $this->product('FEATURED-ACTIVE', ['is_featured' => true]);
        $this->product('FEATURED-INACTIVE', ['is_featured' => true, 'status' => false]);
        $this->product('NEW-ACTIVE', ['is_new' => true]);

        $featured = $this->productDataTable(['filter' => 'featured', 'status' => '1']);
        $new = $this->productDataTable(['filter' => 'new']);

        $featured->assertOk()->assertJsonPath('recordsFiltered', 1);
        $this->assertSame(['FEATURED-ACTIVE'], collect($featured->json('data'))->pluck('sku')->all());
        $new->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.sku', 'NEW-ACTIVE');
    }

    public function test_on_sale_filter_uses_the_existing_special_price_window(): void
    {
        $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');
        $this->travelTo($now);

        $this->product('SALE-ACTIVE', [
            'price' => 10,
            'special_price' => 8,
            'special_price_from' => $now,
            'special_price_to' => $now,
        ]);
        $this->product('SALE-FUTURE', [
            'price' => 10,
            'special_price' => 8,
            'special_price_from' => $now->addSecond(),
        ]);
        $this->product('SALE-EXPIRED', [
            'price' => 10,
            'special_price' => 8,
            'special_price_to' => $now->subSecond(),
        ]);
        $this->product('NOT-DISCOUNTED', ['price' => 10, 'special_price' => 10]);

        $response = $this->productDataTable(['filter' => 'on_sale']);

        $response->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.sku', 'SALE-ACTIVE');
    }

    public function test_zero_price_filter_uses_effective_price(): void
    {
        $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');
        $this->travelTo($now);

        $this->product('ZERO-REGULAR', ['price' => 0]);
        $this->product('ZERO-SPECIAL', ['price' => 10, 'special_price' => 0]);
        $this->product('ZERO-FUTURE', [
            'price' => 10,
            'special_price' => 0,
            'special_price_from' => $now->addSecond(),
        ]);
        $this->product('ZERO-EXPIRED', [
            'price' => 10,
            'special_price' => 0,
            'special_price_to' => $now->subSecond(),
        ]);

        $response = $this->productDataTable(['filter' => 'zero_price']);

        $response->assertOk()->assertJsonPath('recordsFiltered', 2);
        $this->assertEqualsCanonicalizing(
            ['ZERO-REGULAR', 'ZERO-SPECIAL'],
            collect($response->json('data'))->pluck('sku')->all()
        );
    }

    public function test_product_out_of_stock_filter_excludes_configurable_parents(): void
    {
        $this->product('SIMPLE-MISSING');
        $this->product('SIMPLE-ZERO', inventory: ['quantity' => 0]);
        $this->product('SIMPLE-STOCK', inventory: ['quantity' => 1]);
        $this->product('CONFIGURABLE', ['type' => ProductType::Configurable->value]);

        $response = $this->productDataTable(['filter' => 'out_of_stock']);

        $response->assertOk()->assertJsonPath('recordsFiltered', 2);
        $this->assertEqualsCanonicalizing(
            ['SIMPLE-MISSING', 'SIMPLE-ZERO'],
            collect($response->json('data'))->pluck('sku')->all()
        );
    }

    public function test_inventory_stock_filters_use_available_quantity_and_configured_threshold(): void
    {
        $this->product('IN-STOCK', inventory: ['quantity' => 5, 'low_stock_alert' => 1]);
        $this->product('OUT-ZERO', inventory: ['quantity' => 0, 'low_stock_alert' => 1]);
        $this->product('OUT-MISSING');
        $this->product('LOW-BOUNDARY', inventory: ['quantity' => 2, 'low_stock_alert' => 2]);
        $this->product('NO-THRESHOLD', inventory: ['quantity' => 2, 'low_stock_alert' => null]);

        $inStock = $this->inventoryDataTable(['stock' => 'in_stock']);
        $outOfStock = $this->inventoryDataTable(['stock' => 'out_of_stock']);
        $lowStock = $this->inventoryDataTable(['stock' => 'low_stock']);

        $inStock->assertOk()->assertJsonPath('recordsFiltered', 3);
        $outOfStock->assertOk()->assertJsonPath('recordsFiltered', 2);
        $lowStock->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.sku', 'LOW-BOUNDARY');
    }

    public function test_filters_preserve_search_pagination_and_ignore_unknown_values(): void
    {
        $this->product('COMBO-ALPHA', ['is_featured' => true]);
        $this->product('COMBO-BETA', ['is_featured' => true]);
        $this->product('OTHER', ['is_featured' => false]);

        $paged = $this->productDataTable([
            'filter' => 'featured',
            'search' => ['value' => 'COMBO', 'regex' => 'false'],
            'length' => 1,
        ]);
        $unknown = $this->productDataTable(['filter' => 'unknown']);

        $paged->assertOk()->assertJsonPath('recordsFiltered', 2);
        $this->assertCount(1, $paged->json('data'));
        $unknown->assertOk()->assertJsonPath('recordsFiltered', 3);
    }

    public function test_admin_pages_expose_filter_controls_and_datatable_hooks(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('id="product-filter"', false);
        $this->get(route('admin.inventory.index'))
            ->assertOk()
            ->assertSee('id="inventory-stock-filter"', false);

        $productScript = file_get_contents(resource_path('js/admin/products.js'));
        $inventoryScript = file_get_contents(resource_path('js/admin/inventory.js'));
        $this->assertStringContainsString("data.filter = document.getElementById('product-filter').value", $productScript);
        $this->assertStringContainsString("data.stock = document.getElementById('inventory-stock-filter').value", $inventoryScript);
    }

    private function productDataTable(array $overrides = []): TestResponse
    {
        return $this->dataTableRequest('admin.products.index', $overrides);
    }

    private function inventoryDataTable(array $overrides = []): TestResponse
    {
        return $this->dataTableRequest('admin.inventory.index', $overrides);
    }

    private function dataTableRequest(string $routeName, array $overrides): TestResponse
    {
        $parameters = array_replace_recursive([
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => '', 'regex' => 'false'],
        ], $overrides);

        return $this->actingAs(User::factory()->create(), 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route($routeName, $parameters));
    }

    private function product(string $sku, array $attributes = [], ?array $inventory = null): Product
    {
        $product = Product::factory()->create(array_merge(['sku' => $sku], $attributes));
        $product->translations()->create([
            'locale' => 'en',
            'name' => $sku,
            'url_key' => strtolower($sku),
        ]);

        if ($inventory !== null) {
            $product->inventory()->create(array_merge([
                'quantity' => 1,
                'average_cost' => 1,
                'low_stock_alert' => null,
            ], $inventory));
        }

        return $product;
    }
}
