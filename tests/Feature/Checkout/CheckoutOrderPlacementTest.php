<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_places_complete_pending_order_without_inventory_mutation(): void
    {
        [$cart, $product, $customer, $shipping, $payment] = $this->scenario();
        $beforeQuantity = $product->inventory->quantity;

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertTrue($result->successful);
        $order = $result->order;
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-\d{6}$/', $order->order_number);
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('unfulfilled', $order->fulfillment_status);
        $this->assertSame('100.0000', $order->subtotal);
        $this->assertSame('10.0000', $order->tax_total);
        $this->assertSame('5.0000', $order->shipping_total);
        $this->assertSame('115.0000', $order->grand_total);
        $this->assertCount(2, $order->addresses);
        $this->assertSame($shipping->code, $order->shipping->shipping_method_code);
        $this->assertEquals('100.0000', $order->items->first()->unit_price);
        $this->assertEquals('10.0000', $order->items->first()->tax_amount);
        $this->assertSame($payment->code, $order->payment->method_code);
        $this->assertSame('115.0000', $order->payment->amount);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame($beforeQuantity, $product->inventory()->first()->quantity);
        $this->assertDatabaseHas('carts', ['id' => $cart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertNotNull($cart->fresh()->last_activity_at);
    }

    public function test_guest_checkout_respects_setting_and_hash_ownership(): void
    {
        [$cart, , , $shipping, $payment] = $this->scenario(guest: true);
        $token = 'a'.str_repeat('1', 63);
        $cart->update(['guest_token_hash' => app(GuestCartTokenService::class)->hash($token)]);
        $this->setting('checkout', 'allow_guest_checkout', '0', 'boolean');

        $blocked = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            null,
            $token
        );

        $this->assertSame(['guest_checkout_disabled'], $blocked->failureCodes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);

        $this->setting('checkout', 'allow_guest_checkout', '1', 'boolean');
        $placed = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            null,
            $token
        );

        $this->assertTrue($placed->successful);
        $this->assertNull($placed->order->user_id);
    }

    public function test_wrong_cart_owner_is_rejected_without_changes(): void
    {
        [$cart, , , $shipping, $payment] = $this->scenario();
        $otherCustomer = User::factory()->customer()->create();

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $otherCustomer
        );

        $this->assertSame(['cart_ownership_mismatch'], $result->failureCodes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_locked_validation_returns_expected_failures_without_order(): void
    {
        [$cart, $product, $customer, $shipping, $payment] = $this->scenario();
        $product->inventory()->update(['quantity' => 0]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertContains('insufficient_stock', $result->failureCodes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_checkout_uses_current_locked_price_and_special_tax_rules(): void
    {
        [$cart, $product, $customer, $shipping, $payment] = $this->scenario();
        $product->update([
            'price' => '120.0000',
            'special_price' => '80.0000',
            'special_price_from' => now()->subMinute(),
            'special_price_to' => now()->addMinute(),
        ]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $item = $result->order->items->first();
        $this->assertEquals('120.0000', $item->original_unit_price);
        $this->assertEquals('80.0000', $item->unit_price);
        $this->assertEquals('8.0000', $item->tax_amount);
        $this->assertSame('93.0000', $result->order->grand_total);
    }

    public function test_configurable_line_snapshots_variant_and_current_options(): void
    {
        [$cart, $standalone, $customer, $shipping, $payment] = $this->scenario();
        $cart->items()->delete();
        $attribute = Attribute::factory()->create([
            'code' => 'color', 'type' => 'select', 'is_configurable' => true, 'is_active' => true,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);
        $option = $attribute->options()->create(['code' => 'black', 'sort_order' => 1]);
        $option->translations()->create(['locale' => 'en', 'label' => 'Black']);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $parent->translations()->create([
            'locale' => 'en', 'name' => 'Configured Product', 'url_key' => 'configured-'.$parent->id,
        ]);
        $parent->superAttributes()->create(['attribute_id' => $attribute->id])
            ->options()->sync([$option->id]);
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'price' => '75.0000',
            'status' => true,
            'is_visible_individually' => false,
        ]);
        $variant->inventory()->create([
            'quantity' => '4.0000', 'average_cost' => '20.0000', 'low_stock_alert' => '1.0000',
        ]);
        $variant->attributeValues()->create([
            'attribute_id' => $attribute->id, 'attribute_option_id' => $option->id,
        ]);
        $cart->items()->create([
            'product_id' => $variant->id,
            'product_type' => CartItemType::Configurable,
            'configuration_hash' => hash('sha256', 'configured-'.$variant->id),
            'quantity' => '1.0000',
        ]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertTrue($result->successful);
        $orderItem = $result->order->items->first();
        $this->assertSame($variant->id, $orderItem->product_id);
        $this->assertSame('variant', $orderItem->product_type);
        $this->assertSame('Configured Product', $orderItem->name);
        $this->assertSame('Color: Black', $orderItem->option_summary);
        $this->assertDatabaseHas('order_item_options', [
            'order_item_id' => $orderItem->id,
            'attribute_code' => 'color',
            'option_code' => 'black',
        ]);
        $this->assertDatabaseHas('products', ['id' => $standalone->id]);
    }

    private function scenario(bool $guest = false): array
    {
        $tax = Tax::create(['name' => 'Standard Tax', 'rate' => 10, 'status' => true]);
        $this->setting('currency', 'default_currency', 'USD', 'string');
        $this->setting('tax', 'tax_mode', 'b2c', 'string');
        $this->setting('tax', 'default_tax_id', (string) $tax->id, 'integer');
        $this->setting('cart', 'lifetime_days', '30', 'integer');
        $customer = User::factory()->customer()->create();
        $cart = Cart::create([
            'user_id' => $guest ? null : $customer->id,
            'guest_token_hash' => $guest ? hash('sha256', fake()->uuid()) : null,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => '100.0000',
            'use_default_tax' => true,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Checkout Product',
            'url_key' => 'checkout-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000',
            'average_cost' => '20.0000',
            'low_stock_alert' => '1.0000',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);
        $shipping = ShippingMethod::factory()->create(['amount' => '5.0000', 'is_active' => true]);
        $payment = PaymentMethod::factory()->create(['is_active' => true]);

        return [$cart, $product->load('inventory'), $customer, $shipping, $payment];
    }

    private function checkoutData(ShippingMethod $shipping, PaymentMethod $payment): array
    {
        $address = [
            'first_name' => 'Jane',
            'last_name' => 'Customer',
            'company' => null,
            'email' => 'jane@example.com',
            'phone' => '70123456',
            'address_line_1' => 'Main Street',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => 'Beirut',
            'postal_code' => null,
            'country_code' => 'LB',
        ];

        return [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => [
                'first_name' => 'Jane',
                'last_name' => 'Customer',
                'phone' => '70123456',
                'email' => 'jane@example.com',
            ],
            'billing_address' => $address,
            'shipping_address' => $address,
        ];
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
        cache()->forget("setting.{$group}.{$key}");
    }
}
