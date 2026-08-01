<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ConfigurablePriceRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_and_different_prices_render_as_single_value_or_range(): void
    {
        [$parent, $attribute, $options] = $this->parent();
        $this->variant($parent, $attribute, $options[0], 10);
        $this->variant($parent, $attribute, $options[1], 15);

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('$ 10.00 – $ 15.00');

        Product::query()->where('configurable_id', $parent->id)->update(['price' => 10]);
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('$ 10.00')
            ->assertDontSee('$ 10.00 – $ 10.00');
    }

    public function test_special_windows_and_regular_range_use_existing_price_rules(): void
    {
        [$parent, $attribute, $options] = $this->parent();
        $special = $this->variant($parent, $attribute, $options[0], 10, [
            'special_price' => 8,
            'special_price_from' => now()->subMinute(),
            'special_price_to' => now()->addMinute(),
        ]);
        $future = $this->variant($parent, $attribute, $options[1], 20, [
            'special_price' => 5,
            'special_price_from' => now()->addDay(),
        ]);
        $expired = $this->variant($parent, $attribute, $options[2], 30, [
            'special_price' => 4,
            'special_price_from' => now()->subDays(2),
            'special_price_to' => now()->subDay(),
        ]);
        $parent->load(['superAttributes', 'variants.attributeValues', 'variants.tax']);
        $range = $parent->configurablePriceRange(
            $parent->eligibleStorefrontVariants(),
            'b2b'
        );

        $this->assertSame(8.0, $range['minimum']);
        $this->assertSame(30.0, $range['maximum']);
        $this->assertSame(10.0, $range['regular_minimum']);
        $this->assertSame(30.0, $range['regular_maximum']);
        $this->assertTrue($range['show_regular_range']);
        $this->assertTrue($special->fresh()->hasActiveSpecialPrice());
        $this->assertFalse($future->fresh()->hasActiveSpecialPrice());
        $this->assertFalse($expired->fresh()->hasActiveSpecialPrice());

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('$ 8.00 – $ 30.00')
            ->assertSee('$ 10.00 – $ 30.00')
            ->assertSee('text-decoration-line-through', false);
    }

    public function test_inactive_zero_and_incomplete_variants_are_excluded_but_out_of_stock_is_included(): void
    {
        [$parent, $attribute, $options] = $this->parent();
        $included = $this->variant($parent, $attribute, $options[0], 12, stock: 0);
        $this->variant($parent, $attribute, $options[1], 0);
        $this->variant($parent, $attribute, $options[2], 5, ['status' => false]);
        Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'status' => true,
            'price' => 2,
        ]);
        $parent->load(['superAttributes', 'variants.attributeValues']);

        $eligible = $parent->eligibleStorefrontVariants();

        $this->assertCount(1, $eligible);
        $this->assertTrue($eligible->first()->is($included));
    }

    public function test_tax_modes_use_variant_display_helpers_and_only_report_a_common_rate(): void
    {
        $tax = Tax::query()->create(['name' => 'Tax', 'rate' => 11, 'status' => true]);
        [$parent, $attribute, $options] = $this->parent();
        $this->variant($parent, $attribute, $options[0], 10, ['use_default_tax' => true]);
        $this->variant($parent, $attribute, $options[1], 15, ['use_default_tax' => true]);
        $parent->load(['superAttributes', 'variants.attributeValues', 'variants.tax']);
        $variants = $parent->eligibleStorefrontVariants();

        $b2b = $parent->configurablePriceRange($variants, 'b2b', $tax);
        $b2c = $parent->configurablePriceRange($variants, 'b2c', $tax);

        $this->assertSame(10.0, $b2b['minimum']);
        $this->assertSame(15.0, $b2b['maximum']);
        $this->assertSame(11.1, $b2c['minimum']);
        $this->assertSame(16.65, $b2c['maximum']);
        $this->assertSame(11.0, $b2c['common_tax_rate']);

        $parent->load(['translations', 'categories', 'images', 'inventory']);
        $html = Blade::render(
            '<x-shop.product-card :product="$product" currency-code="USD" tax-mode="b2c" :default-tax="$tax" />',
            ['product' => $parent, 'tax' => $tax]
        );
        $this->assertStringContainsString('$ 11.10 – $ 16.65', $html);
        $this->assertStringContainsString('Including 11% tax', $html);

        $otherTax = Tax::query()->create(['name' => 'Other', 'rate' => 5, 'status' => true]);
        $variants->last()->update(['use_default_tax' => false, 'tax_id' => $otherTax->id]);
        $variants->last()->setRelation('tax', $otherTax);
        $mixed = $parent->configurablePriceRange($variants, 'b2c', $tax);
        $this->assertNull($mixed['common_tax_rate']);
        $mixedHtml = Blade::render(
            '<x-shop.product-card :product="$product" currency-code="USD" tax-mode="b2c" :default-tax="$tax" />',
            ['product' => $parent, 'tax' => $tax]
        );
        $this->assertStringNotContainsString('Including 11% tax', $mixedHtml);
    }

    public function test_product_details_starts_with_range_and_javascript_preserves_it_until_selection(): void
    {
        [$parent, $attribute, $options] = $this->parent();
        $this->variant($parent, $attribute, $options[0], 10);
        $this->variant($parent, $attribute, $options[1], 15);

        $this->get(route('shop.products.show', 'range-product'))
            ->assertOk()
            ->assertSee('$ 10.00 – $ 15.00')
            ->assertSee('"price":"$ 10.00"', false)
            ->assertSee('"price":"$ 15.00"', false);

        $script = file_get_contents(resource_path('js/shop/configurable-product.js'));
        $this->assertStringContainsString('initialPriceNodes', $script);
        $this->assertStringContainsString('node.cloneNode(true)', $script);
    }

    public function test_variant_resolution_refuses_to_lazy_load_relations(): void
    {
        $parent = Product::factory()->create(['type' => ProductType::Configurable->value]);

        $this->expectException(LogicException::class);
        $parent->eligibleStorefrontVariants();
    }

    public function test_homepage_range_queries_do_not_grow_with_variant_count(): void
    {
        [$parent, $attribute, $options] = $this->parent();
        $this->variant($parent, $attribute, $options[0], 10);
        setting('currency.default_currency', 'USD');
        setting('tax.tax_mode', 'b2c');
        setting('tax.default_tax_id');
        $phase = 'first';
        $counts = ['first' => 0, 'second' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            $sql = strtolower($query->sql);
            $isConfigurableRelationQuery = str_contains($sql, 'configurable_id')
                || str_contains($sql, 'product_attribute_values')
                || str_contains($sql, 'from "taxes"')
                || str_contains($sql, 'from `taxes`');

            if ($isConfigurableRelationQuery && in_array($phase, ['first', 'second'], true)) {
                $counts[$phase]++;
            }
        });

        $this->get(route('shop.home'))->assertOk();
        $phase = 'setup';
        foreach (range(1, 8) as $index) {
            $option = $attribute->options()->create([
                'code' => 'extra-'.$index,
                'sort_order' => 10 + $index,
            ]);
            $this->variant($parent, $attribute, $option, 10 + $index);
        }
        $phase = 'second';
        $this->get(route('shop.home'))->assertOk();

        $this->assertSame($counts['first'], $counts['second']);
    }

    private function parent(): array
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $options = collect(['first', 'second', 'third'])->map(
            fn (string $code, int $index) => $attribute->options()->create([
                'code' => $code,
                'sort_order' => $index,
            ])
        )->all();
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => 'Range Product',
            'url_key' => 'range-product',
        ]);
        $parent->superAttributes()->create([
            'attribute_id' => $attribute->id,
        ])->options()->sync(collect($options)->pluck('id'));

        return [$parent, $attribute, $options];
    }

    private function variant(
        Product $parent,
        Attribute $attribute,
        $option,
        float $price,
        array $state = [],
        int $stock = 5
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
        $variant->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 1,
            'low_stock_alert' => 1,
        ]);

        return $variant;
    }
}
