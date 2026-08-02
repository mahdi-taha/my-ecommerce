<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use DOMDocument;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomepageOnSaleProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_only_active_positive_current_simple_sales_render_in_sale_tab(): void
    {
        $customer = User::factory()->customer()->create();
        $this->product('Active Sale', ['special_price' => 8]);
        $this->product('Future Sale', [
            'special_price' => 8,
            'special_price_from' => now()->addDay(),
        ]);
        $this->product('Expired Sale', [
            'special_price' => 8,
            'special_price_to' => now()->subDay(),
        ]);
        $this->product('Equal Price', ['special_price' => 10]);
        $this->product('Zero Special', ['special_price' => 0]);
        $this->product('Inactive Sale', ['special_price' => 8, 'status' => false]);

        $response = $this->actingAs($customer, 'customer')->get(route('shop.home'))->assertOk();
        $pane = $this->salePane($response->getContent());

        $response->assertSee(__('shop.home.on_sale'));
        $this->assertStringContainsString('Active Sale', $pane);
        $this->assertStringNotContainsString('Future Sale', $pane);
        $this->assertStringNotContainsString('Expired Sale', $pane);
        $this->assertStringNotContainsString('Equal Price', $pane);
        $this->assertStringNotContainsString('Zero Special', $pane);
        $this->assertStringNotContainsString('Inactive Sale', $pane);
        $this->assertStringContainsString('data-product-card-cart-form', $pane);
        $this->assertStringContainsString('data-product-card-wishlist-form', $pane);
    }

    public function test_configurable_sale_requires_an_eligible_sale_variant(): void
    {
        [$saleParent, $attribute, $options] = $this->parent('Configurable Sale');
        $this->variant($saleParent, $attribute, $options[0], ['special_price' => 8], stock: 0);

        [$futureParent, $futureAttribute, $futureOptions] = $this->parent('Future Configurable');
        $this->variant($futureParent, $futureAttribute, $futureOptions[0], [
            'special_price' => 8,
            'special_price_from' => now()->addDay(),
        ]);

        [$zeroParent, $zeroAttribute, $zeroOptions] = $this->parent('Zero Configurable');
        $this->variant($zeroParent, $zeroAttribute, $zeroOptions[0], ['special_price' => 0]);

        [$inactiveParent, $inactiveAttribute, $inactiveOptions] = $this->parent('Inactive Configurable');
        $this->variant($inactiveParent, $inactiveAttribute, $inactiveOptions[0], [
            'special_price' => 8,
            'status' => false,
        ]);

        [$incompleteParent] = $this->parent('Incomplete Configurable');
        Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $incompleteParent->id,
            'is_visible_individually' => false,
            'price' => 10,
            'special_price' => 8,
        ]);

        $pane = $this->salePane($this->get(route('shop.home'))->assertOk()->getContent());

        $this->assertStringContainsString('Configurable Sale', $pane);
        $this->assertStringNotContainsString('Future Configurable', $pane);
        $this->assertStringNotContainsString('Zero Configurable', $pane);
        $this->assertStringNotContainsString('Inactive Configurable', $pane);
        $this->assertStringNotContainsString('Incomplete Configurable', $pane);
    }

    public function test_sale_tab_is_hidden_without_qualified_products(): void
    {
        $this->product('Regular Product');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('on-sale-products', false)
            ->assertDontSee(__('shop.home.on_sale'));
    }

    public function test_sale_products_are_newest_first_then_id_and_limited_to_eight(): void
    {
        $createdAt = now()->subDay();
        $products = collect(range(1, 9))->map(fn (int $index) => $this->product(
            'Sale '.$index,
            ['special_price' => 8, 'created_at' => $createdAt, 'updated_at' => $createdAt]
        ));

        $pane = $this->salePane($this->get(route('shop.home'))->assertOk()->getContent());
        $expected = $products->sortByDesc('id')->take(8)->map(
            fn (Product $product): string => $product->translations()->first()->name
        );
        $excluded = $products->sortBy('id')->first()->translations()->first()->name;

        foreach ($expected as $name) {
            $this->assertStringContainsString($name, $pane);
        }
        $this->assertStringNotContainsString($excluded, $pane);
    }

    public function test_sale_relationship_queries_do_not_grow_with_variant_count(): void
    {
        [$parent, $attribute, $options] = $this->parent('Bounded Sale');
        $this->variant($parent, $attribute, $options[0], ['special_price' => 8]);
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
        foreach (range(1, 6) as $index) {
            $option = $attribute->options()->create([
                'code' => 'extra-'.$index,
                'sort_order' => 10 + $index,
            ]);
            $this->variant($parent, $attribute, $option, ['special_price' => 8]);
        }
        $phase = 'second';
        $this->get(route('shop.home'))->assertOk();

        $this->assertSame($counts['first'], $counts['second']);
    }

    private function product(string $name, array $state = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'price' => 10,
            'special_price_from' => now()->subDay(),
            'special_price_to' => now()->addDay(),
        ], $state));
        $product->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'url_key' => str($name)->slug(),
        ]);
        $product->inventory()->create([
            'quantity' => 5,
            'average_cost' => 1,
            'low_stock_alert' => 1,
        ]);

        return $product;
    }

    private function parent(string $name): array
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $options = collect(['first', 'second'])->map(
            fn (string $code, int $index) => $attribute->options()->create([
                'code' => $name.'-'.$code,
                'sort_order' => $index,
            ])
        );
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'price' => 10,
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'url_key' => str($name)->slug(),
        ]);
        $parent->superAttributes()->create([
            'attribute_id' => $attribute->id,
        ])->options()->sync($options->pluck('id'));

        return [$parent, $attribute, $options->values()];
    }

    private function variant(
        Product $parent,
        Attribute $attribute,
        $option,
        array $state = [],
        int $stock = 5
    ): Product {
        $variant = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'status' => true,
            'is_visible_individually' => false,
            'price' => 10,
            'special_price_from' => now()->subDay(),
            'special_price_to' => now()->addDay(),
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

    private function salePane(string $content): string
    {
        $document = new DOMDocument;
        @$document->loadHTML($content);
        $pane = $document->getElementById('on-sale-products');

        $this->assertNotNull($pane);

        return (string) $document->saveHTML($pane);
    }
}
