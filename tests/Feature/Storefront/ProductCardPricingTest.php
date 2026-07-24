<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\Tax;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductCardPricingTest extends TestCase
{
    public function test_b2b_card_displays_tax_exclusive_price_and_tax_label(): void
    {
        $html = $this->renderCard(
            product: $this->product(),
            taxMode: 'b2b',
            defaultTax: $this->tax(11)
        );

        $this->assertStringContainsString('$ 900.00', $html);
        $this->assertStringContainsString('+ 11% Tax at checkout', $html);
        $this->assertStringNotContainsString('$ 999.00', $html);
    }

    public function test_b2c_card_displays_tax_inclusive_price_and_tax_label(): void
    {
        $html = $this->renderCard(
            product: $this->product(),
            taxMode: 'b2c',
            defaultTax: $this->tax(11)
        );

        $this->assertStringContainsString('$ 999.00', $html);
        $this->assertStringContainsString('Including 11% tax', $html);
        $this->assertStringNotContainsString('+ 11% Tax at checkout', $html);
    }

    public function test_active_special_price_and_crossed_out_regular_price_follow_tax_mode(): void
    {
        $html = $this->renderCard(
            product: $this->product([
                'price' => 1000,
                'special_price' => 900,
                'special_price_from' => now()->subDay(),
                'special_price_to' => now()->addDay(),
            ]),
            taxMode: 'b2c',
            defaultTax: $this->tax(11)
        );

        $this->assertStringContainsString('$ 999.00', $html);
        $this->assertStringContainsString('$ 1,110.00', $html);
        $this->assertStringContainsString('text-decoration-line-through', $html);
        $this->assertStringContainsString('Including 11% tax', $html);
    }

    public function test_zero_or_inactive_tax_displays_no_tax_label(): void
    {
        foreach ([$this->tax(0), $this->tax(11, false)] as $tax) {
            $html = $this->renderCard(
                product: $this->product(),
                taxMode: 'b2c',
                defaultTax: $tax
            );

            $this->assertStringContainsString('$ 900.00', $html);
            $this->assertStringNotContainsString('Including', $html);
            $this->assertStringNotContainsString('Tax at checkout', $html);
        }
    }

    private function renderCard(Product $product, string $taxMode, ?Tax $defaultTax): string
    {
        return Blade::render(
            '<x-shop.product-card :product="$product" currency-code="USD" :tax-mode="$taxMode" :default-tax="$defaultTax" />',
            compact('product', 'taxMode', 'defaultTax')
        );
    }

    private function product(array $attributes = []): Product
    {
        $product = new Product(array_merge([
            'sku' => 'TEST-SIMPLE',
            'price' => 900,
            'special_price' => null,
            'use_default_tax' => true,
            'is_new' => false,
            'is_featured' => false,
        ], $attributes));

        $product->setRelation('translations', collect([
            new ProductTranslation([
                'locale' => 'en',
                'name' => 'Test Product',
                'url_key' => 'test-product',
            ]),
        ]));
        $product->setRelation('categories', collect());
        $product->setRelation('images', collect());
        $product->setRelation('tax', null);

        return $product;
    }

    private function tax(float $rate, bool $active = true): Tax
    {
        return new Tax([
            'name' => 'Test Tax',
            'rate' => $rate,
            'status' => $active,
        ]);
    }
}
