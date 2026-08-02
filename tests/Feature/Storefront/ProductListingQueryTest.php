<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductListingQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_listing_queries_remain_bounded_as_products_and_variants_grow(): void
    {
        $this->configurable('First', 1);
        $phase = 'first';
        $counts = ['first' => 0, 'second' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            $sql = strtolower($query->sql);
            $catalogQuery = str_contains($sql, 'products')
                || str_contains($sql, 'product_translations')
                || str_contains($sql, 'product_images')
                || str_contains($sql, 'product_inventories')
                || str_contains($sql, 'product_super_attributes')
                || str_contains($sql, 'product_attribute_values')
                || str_contains($sql, 'product_categories');

            if ($catalogQuery && in_array($phase, ['first', 'second'], true)) {
                $counts[$phase]++;
            }
        });

        $this->get(route('shop.products.index'))->assertOk();
        $phase = 'setup';
        foreach (range(1, 8) as $index) {
            $this->configurable('Extra '.$index, 3);
        }
        $phase = 'second';
        $this->get(route('shop.products.index'))->assertOk();

        $this->assertSame($counts['first'], $counts['second']);
    }

    public function test_navbar_and_listing_share_one_two_query_category_hierarchy(): void
    {
        foreach (range(1, 4) as $index) {
            $category = Category::factory()->create(['position' => $index]);
            $category->translations()->create([
                'locale' => 'en',
                'name' => 'Category '.$index,
                'slug' => 'category-'.$index,
            ]);
        }
        $categoryQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'from "categories"')
                || str_contains($sql, 'from "category_translations"')
                || str_contains($sql, 'from `categories`')
                || str_contains($sql, 'from `category_translations`')) {
                $categoryQueries++;
            }
        });

        $this->get(route('shop.products.index'))->assertOk();

        $this->assertSame(2, $categoryQueries);
    }

    private function configurable(string $name, int $variantCount): void
    {
        $attribute = Attribute::factory()->create(['type' => 'select', 'is_configurable' => true]);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'url_key' => str($name)->slug().'-'.$parent->id,
        ]);
        $super = $parent->superAttributes()->create(['attribute_id' => $attribute->id]);

        foreach (range(1, $variantCount) as $index) {
            $option = $attribute->options()->create(['code' => "{$parent->id}-{$index}", 'sort_order' => $index]);
            $super->options()->attach($option);
            $variant = Product::factory()->create([
                'type' => ProductType::Simple->value,
                'configurable_id' => $parent->id,
                'is_visible_individually' => false,
                'price' => 10 + $index,
            ]);
            $variant->attributeValues()->create([
                'attribute_id' => $attribute->id,
                'attribute_option_id' => $option->id,
            ]);
            $variant->inventory()->create(['quantity' => 5, 'average_cost' => 1]);
        }
    }
}
