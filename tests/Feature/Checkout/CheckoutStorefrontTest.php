<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\PaymentMethodType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_checkout_displays_active_supported_methods_and_summary(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        ShippingMethod::factory()->create(['name' => 'Inactive Shipping', 'is_active' => false]);
        PaymentMethod::factory()->create(['name' => 'Gateway Card', 'type' => PaymentMethodType::Gateway, 'is_active' => true]);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.checkout.show'))
            ->assertOk()
            ->assertSee($shipping->name)
            ->assertSee($payment->name)
            ->assertDontSee('Inactive Shipping')
            ->assertDontSee('Gateway Card')
            ->assertSee('115.00')
            ->assertSee('Checkout Product');

        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_allowed_guest_can_open_checkout_and_empty_cart_redirects(): void
    {
        [$cart, , , , $token] = $this->scenario(guest: true);

        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.show'))
            ->assertOk();

        $cart->items()->delete();

        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.show'))
            ->assertRedirect(route('shop.cart.index'));
    }

    public function test_authenticated_http_placement_creates_snapshots_and_redirects_once(): void
    {
        [$cart, $customer, $shipping, $payment, , $product] = $this->scenario();
        $beforeQuantity = $product->inventory->quantity;

        $response = $this->actingAs($customer, 'customer')
            ->post(route('shop.checkout.store'), $this->checkoutData($shipping, $payment));

        $order = $customer->orders()->firstOrFail();
        $response->assertRedirect(route('shop.checkout.success', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_addresses', 2);
        $this->assertDatabaseCount('order_shipping', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
        $this->assertSame($beforeQuantity, $product->inventory()->first()->quantity);

        $this->get(route('shop.checkout.success', $order))
            ->assertOk()
            ->assertSee($order->order_number);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_guest_placement_and_confirmation_require_same_guest_identity(): void
    {
        [, , $shipping, $payment, $token] = $this->scenario(guest: true);

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->post(route('shop.checkout.store'), $this->checkoutData($shipping, $payment));
        $order = Order::query()->firstOrFail();

        $response->assertRedirect(route('shop.checkout.success', $order));
        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.success', $order))
            ->assertOk();
        $this->withCookie(GuestCartTokenService::COOKIE_NAME, str_repeat('b', 64))
            ->get(route('shop.checkout.success', $order))
            ->assertForbidden();
    }

    public function test_confirmation_rejects_other_customer_and_anonymous_access(): void
    {
        [, $customer, $shipping, $payment] = $this->scenario();
        $this->actingAs($customer, 'customer')
            ->post(route('shop.checkout.store'), $this->checkoutData($shipping, $payment));
        $order = $customer->orders()->firstOrFail();

        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('shop.checkout.success', $order))
            ->assertForbidden();
        auth('customer')->logout();
        $this->get(route('shop.checkout.success', $order))->assertForbidden();
    }

    public function test_guest_disabled_redirects_display_and_rejects_placement_safely(): void
    {
        [, , $shipping, $payment, $token] = $this->scenario(guest: true);
        $this->setting('checkout', 'allow_guest_checkout', '0', 'boolean');

        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.show'))
            ->assertRedirect(route('customer.login'));
        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->post(route('shop.checkout.store'), $this->checkoutData($shipping, $payment))
            ->assertRedirect(route('customer.login'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_validation_rejects_gateway_method_and_preserves_input(): void
    {
        [, $customer, $shipping] = $this->scenario();
        $gateway = PaymentMethod::factory()->create([
            'type' => PaymentMethodType::Gateway,
            'is_active' => true,
        ]);
        $data = $this->checkoutData($shipping, $gateway);
        $data['customer']['first_name'] = 'Preserved';

        $this->actingAs($customer, 'customer')
            ->from(route('shop.checkout.show'))
            ->post(route('shop.checkout.store'), $data)
            ->assertRedirect(route('shop.checkout.show'))
            ->assertSessionHasErrors('payment_method')
            ->assertSessionHasInput('customer.first_name', 'Preserved');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_configurable_options_are_displayed_and_persisted_through_http_checkout(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        $cart->items()->delete();
        $attribute = Attribute::factory()->create([
            'code' => 'color', 'type' => 'select', 'is_configurable' => true, 'is_active' => true,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);
        $option = $attribute->options()->create(['code' => 'black', 'sort_order' => 1]);
        $option->translations()->create(['locale' => 'en', 'label' => 'Black']);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value, 'status' => true, 'is_visible_individually' => true,
        ]);
        $parent->translations()->create([
            'locale' => 'en', 'name' => 'Configured Product', 'url_key' => 'configured-'.$parent->id,
        ]);
        $parent->superAttributes()->create(['attribute_id' => $attribute->id])->options()->sync([$option->id]);
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value, 'configurable_id' => $parent->id,
            'price' => '50.0000', 'status' => true, 'is_visible_individually' => false,
        ]);
        $variant->inventory()->create([
            'quantity' => '5.0000', 'average_cost' => '10.0000', 'low_stock_alert' => '1.0000',
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

        $this->actingAs($customer, 'customer')->get(route('shop.checkout.show'))
            ->assertOk()->assertSee('Configured Product')->assertSee('Color')->assertSee('Black');
        $this->post(route('shop.checkout.store'), $this->checkoutData($shipping, $payment));

        $this->assertDatabaseHas('order_item_options', [
            'attribute_code' => 'color', 'option_code' => 'black',
        ]);
    }

    private function scenario(bool $guest = false): array
    {
        $this->setting('checkout', 'allow_guest_checkout', '1', 'boolean');
        $this->setting('currency', 'default_currency', 'USD', 'string');
        $this->setting('tax', 'tax_mode', 'b2c', 'string');
        $tax = Tax::create(['name' => 'Tax', 'rate' => 10, 'status' => true]);
        $this->setting('tax', 'default_tax_id', (string) $tax->id, 'integer');
        $this->setting('cart', 'lifetime_days', '30', 'integer');
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
            'locale' => 'en', 'name' => 'Checkout Product', 'url_key' => 'checkout-'.$product->id,
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
        $shipping = ShippingMethod::factory()->create(['name' => 'Beirut Delivery', 'amount' => '5.0000', 'is_active' => true]);
        $payment = PaymentMethod::factory()->create([
            'name' => 'Cash on Delivery', 'type' => PaymentMethodType::Offline, 'is_active' => true,
        ]);

        return [$cart, $customer, $shipping, $payment, $token, $product->load('inventory')];
    }

    private function checkoutData(ShippingMethod $shipping, PaymentMethod $payment): array
    {
        $address = [
            'first_name' => 'Jane', 'last_name' => 'Customer', 'company' => null,
            'email' => 'jane@example.com', 'phone' => '70123456',
            'address_line_1' => 'Main Street', 'address_line_2' => null,
            'city' => 'Beirut', 'state' => 'Beirut', 'postal_code' => null, 'country_code' => 'LB',
        ];

        return [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => ['first_name' => 'Jane', 'last_name' => 'Customer', 'phone' => '70123456', 'email' => 'jane@example.com'],
            'billing_address' => $address,
            'shipping_address' => $address,
        ];
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
        cache()->forget("setting.{$group}.{$key}");
    }
}
