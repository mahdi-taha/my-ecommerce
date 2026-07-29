<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\PaymentMethodType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSummaryEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_refreshes_authoritative_shipping_totals(): void
    {
        [$cart, $customer, $shippingA, $shippingB, $payment, , $product] = $this->scenario();
        $inventoryBefore = $product->inventory->quantity;

        $first = $this->actingAs($customer, 'customer')->postJson(route('shop.checkout.summary'), [
            'shipping_method' => $shippingA->code,
            'payment_method' => $payment->code,
            'shipping_amount' => '9999.0000',
        ]);
        $second = $this->postJson(route('shop.checkout.summary'), [
            'shipping_method' => $shippingB->code,
            'payment_method' => $payment->code,
            'shipping_amount' => '0.0000',
        ]);

        $first->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('summary.subtotal', '100.0000')
            ->assertJsonPath('summary.tax_total', '10.0000')
            ->assertJsonPath('summary.shipping_amount', '2.0000')
            ->assertJsonPath('summary.grand_total', '112.0000')
            ->assertJsonPath('summary.formatted_grand_total', '$ 112.00');
        $second->assertOk()
            ->assertJsonPath('summary.shipping_amount', '7.0000')
            ->assertJsonPath('summary.grand_total', '117.0000')
            ->assertJsonPath('summary.formatted_shipping_amount', '$ 7.00');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_payments', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id, 'quantity' => 1]);
        $this->assertSame($inventoryBefore, $product->inventory()->first()->quantity);
    }

    public function test_guest_refresh_uses_existing_token_and_default_supported_payment_method(): void
    {
        [, , $shipping, , , $token] = $this->scenario(guest: true);

        $this->withHeaders(['Accept' => 'application/json'])
            ->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->post(route('shop.checkout.summary'), [
                'shipping_method' => $shipping->code,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.shipping_amount', '2.0000');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_shipping_method_is_rejected_safely(): void
    {
        [, $customer, , , $payment] = $this->scenario();
        $inactive = ShippingMethod::factory()->create(['is_active' => false]);

        $this->actingAs($customer, 'customer')->postJson(route('shop.checkout.summary'), [
            'shipping_method' => $inactive->code,
            'payment_method' => $payment->code,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.code', 'shipping_method_unavailable');
    }

    public function test_inactive_and_gateway_payment_methods_are_rejected(): void
    {
        [, $customer, $shipping] = $this->scenario();
        $inactive = PaymentMethod::query()->where('code', 'manual_bank_transfer')->firstOrFail();
        $inactive->update(['is_active' => false]);
        $gateway = PaymentMethod::factory()->create([
            'code' => 'gateway_card',
            'type' => PaymentMethodType::Gateway,
            'is_active' => true,
        ]);

        foreach ([$inactive, $gateway] as $method) {
            $this->actingAs($customer, 'customer')->postJson(route('shop.checkout.summary'), [
                'shipping_method' => $shipping->code,
                'payment_method' => $method->code,
            ])->assertUnprocessable()
                ->assertJsonPath('errors.0.code', 'payment_method_unavailable');
        }
    }

    public function test_empty_cart_is_rejected_without_persistence(): void
    {
        [$cart, $customer, $shipping, , $payment] = $this->scenario();
        $cart->items()->delete();

        $this->actingAs($customer, 'customer')->postJson(route('shop.checkout.summary'), [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'empty_cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inventory_failure_is_returned_as_structured_data(): void
    {
        [, $customer, $shipping, , $payment, , $product] = $this->scenario();
        $product->inventory()->update(['quantity' => '0.0000']);

        $this->actingAs($customer, 'customer')->postJson(route('shop.checkout.summary'), [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.code', 'insufficient_stock');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_checkout_page_exposes_live_summary_frontend_contract(): void
    {
        [, $customer] = $this->scenario();

        $this->actingAs($customer, 'customer')->get(route('shop.checkout.show'))
            ->assertOk()
            ->assertSee(route('shop.checkout.summary'), false)
            ->assertSee('data-checkout-summary', false)
            ->assertSee('data-checkout-subtotal', false)
            ->assertSee('data-checkout-tax-total', false)
            ->assertSee('data-checkout-shipping-amount', false)
            ->assertSee('data-checkout-grand-total', false)
            ->assertSee('data-checkout-place-order', false);
    }

    private function scenario(bool $guest = false): array
    {
        $this->setting('checkout', 'allow_guest_checkout', '1', 'boolean');
        $this->setting('currency', 'default_currency', 'USD', 'string');
        $this->setting('tax', 'tax_mode', 'b2c', 'string');
        $this->setting('cart', 'lifetime_days', '30', 'integer');
        $tax = Tax::create(['name' => 'Tax', 'rate' => 10, 'status' => true]);
        $this->setting('tax', 'default_tax_id', (string) $tax->id, 'integer');
        $customer = User::factory()->customer()->create();
        $token = str_repeat('a', 64);
        $cart = Cart::create([
            'user_id' => $guest ? null : $customer->id,
            'guest_token_hash' => $guest ? hash('sha256', $token) : null,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'price' => '100.0000',
            'use_default_tax' => true,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en', 'name' => 'Summary Product', 'url_key' => 'summary-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000', 'average_cost' => '20.0000', 'low_stock_alert' => '1.0000',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);
        $shippingA = ShippingMethod::factory()->create([
            'code' => 'shipping_a', 'amount' => '2.0000', 'is_active' => true,
        ]);
        $shippingB = ShippingMethod::factory()->create([
            'code' => 'shipping_b', 'amount' => '7.0000', 'is_active' => true,
        ]);
        $payment = PaymentMethod::query()->where('code', 'cash_on_delivery')->firstOrFail();
        $payment->update([
            'type' => PaymentMethodType::Offline,
            'is_active' => true,
        ]);

        return [$cart, $customer, $shippingA, $shippingB, $payment, $token, $product->load('inventory')];
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
        cache()->forget("setting.{$group}.{$key}");
    }
}
