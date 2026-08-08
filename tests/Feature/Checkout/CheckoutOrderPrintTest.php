<?php

namespace Tests\Feature\Checkout;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutOrderPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_checkout_print_reuses_customer_ownership_protection(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.checkout.success.print', ['locale' => 'en', 'order' => $order]))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('content="noindex,nofollow"', false);

        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('shop.checkout.success.print', ['locale' => 'en', 'order' => $order]))
            ->assertForbidden();

        auth('customer')->logout();
        $this->get(route('shop.checkout.success.print', ['locale' => 'en', 'order' => $order]))
            ->assertForbidden();
    }

    public function test_guest_checkout_print_requires_the_same_session_cart_and_cookie_identity(): void
    {
        $token = str_repeat('a', 64);
        $cart = Cart::query()->create([
            'guest_token_hash' => hash('sha256', $token),
            'last_activity_at' => now(),
            'expires_at' => now()->addDay(),
        ]);
        $order = $this->order();
        $session = ['shop.checkout.guest_orders' => [(string) $order->id => $cart->id]];

        $this->withSession($session)
            ->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.success.print', ['locale' => 'en', 'order' => $order]))
            ->assertOk();

        $this->withSession($session)
            ->withCookie(GuestCartTokenService::COOKIE_NAME, str_repeat('b', 64))
            ->get(route('shop.checkout.success.print', ['locale' => 'en', 'order' => $order]))
            ->assertForbidden();

        $this->flushSession()
            ->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.success.print', ['locale' => 'en', 'order' => $order]))
            ->assertForbidden();
    }

    private function order(?User $customer = null): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-PRINT-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'guest@example.test',
            'customer_first_name' => 'Print',
            'customer_last_name' => 'Customer',
            'customer_phone' => '70123456',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ]);
    }
}
