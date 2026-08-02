<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_shop_route_paginates_eligible_root_products_by_twelve(): void
    {
        foreach (range(1, 13) as $index) {
            $this->simple("Product {$index}", 10 + $index, ['created_at' => now()->addSeconds($index)]);
        }
        $this->simple('Inactive Product', 10, ['status' => false]);
        $this->simple('Hidden Product', 10, ['is_visible_individually' => false]);
        $this->simple('Free Product', 0);
        $this->simple('Arabic Only', 10, locale: 'ar');

        $first = $this->get(route('shop.products.index'))->assertOk();
        $first->assertSee('Product 13')->assertDontSee('Product 1</a>', false)
            ->assertDontSee('Inactive Product')->assertDontSee('Hidden Product')
            ->assertDontSee('Free Product')->assertDontSee('Arabic Only');
        $this->assertSame(13, $first->viewData('products')->total());
        $this->assertCount(12, $first->viewData('products')->items());

        $second = $this->get(route('shop.products.index', ['page' => 2]))->assertOk();
        $this->assertCount(1, $second->viewData('products')->items());
        $second->assertSee('Product 1');
    }

    public function test_search_sorting_and_flags_are_applied_in_sql(): void
    {
        $alpha = $this->simple('Alpha Camera', 30, [
            'is_featured' => true,
            'is_new' => true,
            'special_price' => 20,
            'special_price_from' => now()->subMinute(),
            'special_price_to' => now()->addMinute(),
        ], shortDescription: 'Professional photography');
        $this->simple('Beta Phone', 10, shortDescription: 'Portable camera companion');
        $this->simple('Gamma Laptop', 40);

        $response = $this->get(route('shop.products.index', [
            'q' => 'camera',
            'sale' => 1,
            'featured' => 1,
            'new' => 1,
            'sort' => 'price_asc',
        ]))->assertOk();

        $this->assertSame([$alpha->id], $response->viewData('products')->pluck('id')->all());
        $response->assertSee('name="q"', false)
            ->assertSee('value="camera"', false)
            ->assertSee('sale=1', false);

        $names = $this->get(route('shop.products.index', ['sort' => 'name_desc']))
            ->viewData('products')->pluck('translations.0.name')->all();
        $this->assertSame(['Gamma Laptop', 'Beta Phone', 'Alpha Camera'], $names);
    }

    public function test_category_filter_includes_all_active_descendants(): void
    {
        $root = $this->category('Root');
        $child = $this->category('Child', $root);
        $grandchild = $this->category('Grandchild', $child);
        $inactive = $this->category('Inactive Child', $root, false);
        $rootProduct = $this->simple('Root Product', 10);
        $childProduct = $this->simple('Child Product', 11);
        $grandchildProduct = $this->simple('Grandchild Product', 12);
        $inactiveProduct = $this->simple('Inactive Branch Product', 13);
        $rootProduct->categories()->attach($root);
        $childProduct->categories()->attach($child);
        $grandchildProduct->categories()->attach($grandchild);
        $inactiveProduct->categories()->attach($inactive);

        $destination = route('shop.categories.show', $root->translations->first()->slug);
        $this->get(route('shop.products.index', ['category' => $root->id]))
            ->assertRedirect($destination);
        $products = $this->get($destination)->assertOk()->viewData('products');

        $this->assertEqualsCanonicalizing(
            [$rootProduct->id, $childProduct->id, $grandchildProduct->id],
            $products->pluck('id')->all()
        );
    }

    public function test_price_and_stock_filters_use_effective_prices_and_authoritative_inventory(): void
    {
        $regular = $this->simple('Regular', 15, stock: 2);
        $sale = $this->simple('Sale', 30, [
            'special_price' => 12,
            'special_price_from' => now()->subMinute(),
            'special_price_to' => now()->addMinute(),
        ], stock: 3);
        $this->simple('Out', 13, stock: 0);
        $this->simple('Expensive', 50, stock: 5);

        $products = $this->get(route('shop.products.index', [
            'min_price' => '12.0000',
            'max_price' => '15.0000',
            'stock' => 'in',
            'sort' => 'price_asc',
        ]))->assertOk()->viewData('products');

        $this->assertSame([$sale->id, $regular->id], $products->pluck('id')->all());
    }

    public function test_configurable_filters_require_the_same_structural_variant_eligibility(): void
    {
        [$parent, $attribute, $option] = $this->configurable('Eligible Parent');
        $eligible = $this->variant($parent, $attribute, $option, 25, 4, [
            'special_price' => 18,
            'special_price_from' => now()->subMinute(),
            'special_price_to' => now()->addMinute(),
        ]);
        [$invalidParent] = $this->configurable('Incomplete Parent');
        Product::factory()->create([
            'configurable_id' => $invalidParent->id,
            'type' => ProductType::Simple->value,
            'price' => 5,
            'status' => true,
            'is_visible_individually' => false,
        ]);
        [$zeroParent, $zeroAttribute, $zeroOption] = $this->configurable('Zero Parent');
        $this->variant($zeroParent, $zeroAttribute, $zeroOption, 0, 4);

        $products = $this->get(route('shop.products.index', [
            'min_price' => 17,
            'max_price' => 19,
            'sale' => 1,
            'stock' => 'in',
        ]))->assertOk()->viewData('products');

        $this->assertSame([$parent->id], $products->pluck('id')->all());
        $this->assertSame($parent->id, $eligible->configurable_id);
    }

    public function test_invalid_filters_are_rejected_and_canonical_is_the_base_shop_url(): void
    {
        $this->from(route('shop.products.index'))
            ->get(route('shop.products.index', ['sort' => 'unknown', 'min_price' => '1.12345']))
            ->assertRedirect(route('shop.products.index'))
            ->assertSessionHasErrors(['sort', 'min_price']);

        $this->simple('Canonical Product', 10);
        $this->get(route('shop.products.index', ['q' => 'Canonical', 'page' => 1]))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('shop.products.index').'">', false);
    }

    public function test_shop_numeric_category_filter_redirects_to_localized_category_without_rendering_a_selector(): void
    {
        $category = $this->category('Shop Filter');

        $this->get(route('shop.products.index', [
            'category' => $category->id,
            'sort' => 'price_asc',
        ]))->assertRedirect(route('shop.categories.show', [
            'slug' => $category->translations->first()->slug,
            'sort' => 'price_asc',
        ]));
        $this->get(route('shop.products.index'))->assertOk()
            ->assertDontSee('name="category"', false);
    }

    private function simple(
        string $name,
        float $price,
        array $state = [],
        string $locale = 'en',
        ?string $shortDescription = null,
        float $stock = 5,
    ): Product {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => $price,
            'status' => true,
            'is_visible_individually' => true,
        ], $state));
        $product->translations()->create([
            'locale' => $locale,
            'name' => $name,
            'url_key' => str($name)->slug().'-'.$product->id,
            'short_description' => $shortDescription,
        ]);
        $product->inventory()->create(['quantity' => $stock, 'average_cost' => 1]);

        return $product;
    }

    private function category(string $name, ?Category $parent = null, bool $active = true): Category
    {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'level' => $parent ? $parent->level + 1 : 0,
            'status' => $active,
        ]);
        $category->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'slug' => str($name)->slug().'-'.$category->id,
        ]);

        return $category;
    }

    private function configurable(string $name): array
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'is_configurable' => true]);
        $option = $attribute->options()->create(['code' => str($name)->slug(), 'sort_order' => 0]);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'url_key' => str($name)->slug().'-'.$parent->id,
        ]);
        $parent->superAttributes()->create(['attribute_id' => $attribute->id])->options()->sync([$option->id]);

        return [$parent, $attribute, $option];
    }

    private function variant(
        Product $parent,
        Attribute $attribute,
        $option,
        float $price,
        float $stock,
        array $state = [],
    ): Product {
        $variant = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'status' => true,
            'is_visible_individually' => false,
            'price' => $price,
        ], $state));
        $variant->attributeValues()->create([
            'attribute_id' => $attribute->id,
            'attribute_option_id' => $option->id,
        ]);
        $variant->inventory()->create(['quantity' => $stock, 'average_cost' => 1]);

        return $variant;
    }
}
