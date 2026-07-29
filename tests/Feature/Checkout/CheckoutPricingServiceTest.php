<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_price_tax_shipping_and_grand_total_are_calculated(): void
    {
        $tax = Tax::create(['name' => 'Standard Tax', 'rate' => 10, 'status' => true]);
        $this->settings('b2b', $tax);
        [$cart, $product] = $this->cartWithProduct(100, 2);
        $shipping = $this->shipping('5.0000');
        $payment = $this->payment();

        $summary = app(CheckoutService::class)->summarize(
            $cart,
            $shipping->code,
            $payment->code
        );

        $this->assertTrue($summary->isValid());
        $this->assertSame('100.0000', $summary->items[0]['unit_price']);
        $this->assertSame('100.0000', $summary->items[0]['display_unit_price']);
        $this->assertSame('200.0000', $summary->items[0]['subtotal']);
        $this->assertSame('10.0000', $summary->items[0]['tax_rate']);
        $this->assertSame('20.0000', $summary->items[0]['tax_amount']);
        $this->assertSame('220.0000', $summary->items[0]['row_total']);
        $this->assertSame('200.0000', $summary->subtotal);
        $this->assertSame('20.0000', $summary->taxTotal);
        $this->assertSame('5.0000', $summary->shippingAmount);
        $this->assertSame('225.0000', $summary->grandTotal);
        $this->assertSame('USD', $summary->currencyCode);
        $this->assertSame($product->id, $summary->items[0]['product_id']);
    }

    public function test_b2c_changes_display_price_without_changing_financial_totals(): void
    {
        $tax = Tax::create(['name' => 'Standard Tax', 'rate' => 10, 'status' => true]);
        $this->settings('b2c', $tax);
        [$cart] = $this->cartWithProduct(100, 2);
        $shipping = $this->shipping('5.0000');

        $summary = app(CheckoutService::class)->summarize(
            $cart,
            $shipping->code,
            $this->payment()->code
        );

        $this->assertSame('110.0000', $summary->items[0]['display_unit_price']);
        $this->assertSame('100.0000', $summary->items[0]['unit_price']);
        $this->assertSame('200.0000', $summary->subtotal);
        $this->assertSame('20.0000', $summary->taxTotal);
        $this->assertSame('225.0000', $summary->grandTotal);
    }

    public function test_active_special_price_is_used_for_subtotal_and_tax(): void
    {
        $tax = Tax::create(['name' => 'Standard Tax', 'rate' => 10, 'status' => true]);
        $this->settings('b2c', $tax);
        [$cart] = $this->cartWithProduct(100, 2, [
            'special_price' => 80,
            'special_price_from' => now()->subDay(),
            'special_price_to' => now()->addDay(),
        ]);

        $summary = app(CheckoutService::class)->summarize(
            $cart,
            $this->shipping('5.0000')->code,
            $this->payment()->code
        );

        $this->assertSame('80.0000', $summary->items[0]['unit_price']);
        $this->assertSame('88.0000', $summary->items[0]['display_unit_price']);
        $this->assertSame('160.0000', $summary->subtotal);
        $this->assertSame('16.0000', $summary->taxTotal);
        $this->assertSame('181.0000', $summary->grandTotal);
    }

    public function test_expired_special_price_falls_back_to_regular_price(): void
    {
        $this->settings('b2b');
        [$cart] = $this->cartWithProduct(100, 1, [
            'special_price' => 80,
            'special_price_from' => now()->subDays(2),
            'special_price_to' => now()->subDay(),
        ]);

        $summary = app(CheckoutService::class)->summarize(
            $cart,
            $this->shipping('0.0000')->code,
            $this->payment()->code
        );

        $this->assertSame('100.0000', $summary->items[0]['unit_price']);
        $this->assertSame('0.0000', $summary->taxTotal);
        $this->assertSame('100.0000', $summary->grandTotal);
    }

    public function test_product_specific_active_tax_overrides_default_tax(): void
    {
        $defaultTax = Tax::create(['name' => 'Default Tax', 'rate' => 11, 'status' => true]);
        $productTax = Tax::create(['name' => 'Product Tax', 'rate' => 5, 'status' => true]);
        $this->settings('b2b', $defaultTax);
        [$cart] = $this->cartWithProduct(100, 1, [
            'use_default_tax' => false,
            'tax_id' => $productTax->id,
        ]);

        $summary = app(CheckoutService::class)->summarize(
            $cart,
            $this->shipping('0.0000')->code,
            $this->payment()->code
        );

        $this->assertSame('Product Tax', $summary->items[0]['tax_name']);
        $this->assertSame('5.0000', $summary->items[0]['tax_rate']);
        $this->assertSame('5.0000', $summary->taxTotal);
        $this->assertSame('105.0000', $summary->grandTotal);
    }

    public function test_invalid_cart_returns_errors_without_partial_pricing(): void
    {
        $this->settings('b2c');
        [$cart, $product] = $this->cartWithProduct(100, 2);
        $product->update(['status' => false]);

        $summary = app(CheckoutService::class)->summarize(
            $cart,
            $this->shipping('5.0000')->code,
            $this->payment()->code
        );

        $this->assertFalse($summary->isValid());
        $this->assertSame('product_inactive', $summary->errors[0]['code']);
        $this->assertSame([], $summary->items);
        $this->assertSame('0.0000', $summary->grandTotal);
    }

    private function cartWithProduct(
        float $price,
        int $quantity,
        array $state = []
    ): array {
        $cart = Cart::create([
            'guest_token_hash' => hash('sha256', fake()->unique()->uuid()),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => $price,
            'use_default_tax' => true,
            'tax_id' => null,
        ], $state));
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Priced Product',
            'url_key' => 'priced-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => 20,
            'average_cost' => 10,
            'low_stock_alert' => 1,
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => $quantity,
        ]);

        return [$cart, $product];
    }

    private function shipping(string $amount): ShippingMethod
    {
        return ShippingMethod::factory()->create([
            'amount' => $amount,
            'is_active' => true,
        ]);
    }

    private function payment(): PaymentMethod
    {
        return PaymentMethod::factory()->create(['is_active' => true]);
    }

    private function settings(string $taxMode, ?Tax $defaultTax = null): void
    {
        foreach ([
            ['group' => 'currency', 'key' => 'default_currency', 'value' => 'USD', 'type' => 'string'],
            ['group' => 'tax', 'key' => 'tax_mode', 'value' => $taxMode, 'type' => 'string'],
            [
                'group' => 'tax',
                'key' => 'default_tax_id',
                'value' => $defaultTax?->id,
                'type' => 'integer',
            ],
        ] as $setting) {
            Setting::query()->updateOrCreate(
                ['group' => $setting['group'], 'key' => $setting['key']],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
            cache()->forget("setting.{$setting['group']}.{$setting['key']}");
        }
    }
}
