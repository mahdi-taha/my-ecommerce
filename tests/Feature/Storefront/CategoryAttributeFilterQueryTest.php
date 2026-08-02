<?php

namespace Tests\Feature\Storefront;

use App\Enums\AttributeType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CategoryAttributeFilterQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_facet_discovery_and_attribute_filter_queries_remain_bounded(): void
    {
        $category = $this->category();
        [$color, $red] = $this->attribute('color', 'red');
        [$size, $large] = $this->attribute('size', 'large');
        $category->filterableAttributes()->attach([$color->id, $size->id]);
        foreach (range(1, 20) as $index) {
            $product = $this->product($category, 'Product '.$index);
            $this->value($product, $color->id, $red->id);
            $this->value($product, $size->id, $large->id);
        }

        $phase = 'plain';
        $counts = ['plain' => 0, 'filtered' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'products')
                || str_contains($sql, 'attributes')
                || str_contains($sql, 'product_attribute_values')) {
                $counts[$phase]++;
            }
        });

        $this->get(route('shop.categories.show', 'phones'))->assertOk();
        $this->app->forgetScopedInstances();
        $phase = 'filtered';
        $this->get(route('shop.categories.show', [
            'slug' => 'phones',
            'attributes' => ['color' => ['red'], 'size' => ['large']],
        ]))->assertOk();

        $this->assertSame($counts['plain'], $counts['filtered']);
        $this->assertLessThanOrEqual(20, $counts['filtered']);
    }

    private function category(): Category
    {
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => 'en', 'name' => 'Phones', 'slug' => 'phones']);

        return $category;
    }

    private function attribute(string $code, string $optionCode): array
    {
        $attribute = Attribute::factory()->create([
            'code' => $code,
            'type' => AttributeType::Select->value,
            'is_active' => true,
            'is_filterable' => true,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => ucfirst($code)]);
        $option = $attribute->options()->create(['code' => $optionCode]);
        $option->translations()->create(['locale' => 'en', 'label' => ucfirst($optionCode)]);

        return [$attribute, $option];
    }

    private function product(Category $category, string $name): Product
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => 10,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create(['locale' => 'en', 'name' => $name, 'url_key' => str($name)->slug().'-'.$product->id]);
        $product->categories()->attach($category);

        return $product;
    }

    private function value(Product $product, int $attributeId, int $optionId): void
    {
        $product->attributeValues()->create([
            'attribute_id' => $attributeId,
            'attribute_option_id' => $optionId,
        ]);
    }
}
