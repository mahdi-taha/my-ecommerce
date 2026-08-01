<?php

namespace Tests\Feature\Identity;

use App\Models\Cart;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveCustomerSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_customer_continues_on_public_storefront_as_a_guest(): void
    {
        $customer = User::factory()->customer()->create();
        $customerCart = $this->customerCart($customer);
        [$guestCart, $rawToken] = $this->guestCart();
        $this->actingAs($customer, 'customer');
        $customer->update(['is_active' => false]);

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $rawToken)
            ->get(route('shop.cart.index'));

        $response->assertOk()
            ->assertCookieMissing(GuestCartTokenService::COOKIE_NAME);
        $this->assertGuest('customer');
        $this->assertModelExists($customerCart);
        $this->assertModelExists($guestCart);
        $this->assertNull($guestCart->fresh()->user_id);
    }

    public function test_deactivated_customer_is_redirected_from_account_with_localized_message(): void
    {
        app()->setLocale('ar');
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer, 'customer');
        $customer->update(['is_active' => false]);

        $this->get(route('customer.account.edit'))
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('error', __('shop.auth.account_inactive'));

        $this->assertGuest('customer');
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee(__('shop.auth.account_inactive'));
    }

    public function test_stale_customer_logout_preserves_the_authenticated_administrator(): void
    {
        $administrator = User::factory()->create();
        $customer = User::factory()->customer()->create();
        $this->actingAs($administrator, 'admin');
        $this->actingAs($customer, 'customer');
        $customer->update(['is_active' => false]);

        $this->get(route('customer.account.edit'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($administrator, 'admin');
        $this->get(route('admin.customers.index'))->assertOk();
    }

    public function test_manual_customer_stale_session_is_cleared_safely(): void
    {
        $manualCustomer = User::factory()->manualCustomer()->create();

        $this->actingAs($manualCustomer, 'customer')
            ->get(route('shop.cart.index'))
            ->assertOk();

        $this->assertGuest('customer');
    }

    public function test_non_customer_stale_session_is_cleared_safely(): void
    {
        $administrator = User::factory()->create();

        $this->actingAs($administrator, 'customer')
            ->get(route('shop.cart.index'))
            ->assertOk();

        $this->assertGuest('customer');
    }

    public function test_active_customer_behavior_is_unchanged(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.account.edit'))
            ->assertOk()
            ->assertSessionMissing('error');

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_inactive_customer_remains_blocked_from_future_login(): void
    {
        $customer = User::factory()->customer()->inactive()->create([
            'email' => 'inactive-session@example.test',
            'password' => 'password123',
        ]);

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => __('shop.auth.login.invalid_credentials'),
        ]);

        $this->assertGuest('customer');
    }

    private function customerCart(User $customer): Cart
    {
        return Cart::query()->create([
            'user_id' => $customer->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    /** @return array{Cart, string} */
    private function guestCart(): array
    {
        $tokens = app(GuestCartTokenService::class);
        $rawToken = $tokens->generate();
        $cart = Cart::query()->create([
            'guest_token_hash' => $tokens->hash($rawToken),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return [$cart, $rawToken];
    }
}
