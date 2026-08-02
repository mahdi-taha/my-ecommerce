<?php

namespace Tests\Feature\Identity;

use App\Models\CmsPage;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLogoutRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_storefront_destinations_are_preserved(): void
    {
        $customer = User::factory()->customer()->create();
        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->create([
            'locale' => 'en',
            'title' => 'About Us',
            'slug' => 'about-us',
            'body' => 'About the store.',
        ]);
        $destinations = [
            route('shop.home'),
            route('shop.products.show', ['url_key' => 'example-product']),
            route('shop.categories.show', ['slug' => 'electronics']),
            route('shop.pages.show', ['slug' => 'about-us']),
            route('shop.cart.index').'?source=header',
        ];

        foreach ($destinations as $destination) {
            $this->actingAs($customer, 'customer')
                ->post(route('customer.logout'), ['return_to' => $destination])
                ->assertRedirect($destination);
            $this->assertGuest('customer');
        }
    }

    public function test_protected_unknown_external_and_malformed_destinations_fall_back_home(): void
    {
        $customer = User::factory()->customer()->create();
        $destinations = [
            route('customer.account.edit'),
            route('shop.wishlist.index'),
            url('/not-a-real-route'),
            'https://example.com/account',
            '//example.com/account',
            'not a url',
            null,
        ];

        foreach ($destinations as $destination) {
            $this->actingAs($customer, 'customer')
                ->post(route('customer.logout'), ['return_to' => $destination])
                ->assertRedirect(route('shop.home'));
            $this->assertGuest('customer');
        }
    }

    public function test_logout_preserves_admin_guard_and_guest_cart_cookie(): void
    {
        $administrator = User::factory()->create();
        $customer = User::factory()->customer()->create();
        $guestToken = str_repeat('a', 64);
        $this->actingAs($administrator, 'admin');
        $this->actingAs($customer, 'customer');

        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $guestToken)
            ->post(route('customer.logout'), ['return_to' => route('shop.cart.index')])
            ->assertRedirect(route('shop.cart.index'))
            ->assertCookieMissing(GuestCartTokenService::COOKIE_NAME);

        $this->assertGuest('customer');
        $this->assertAuthenticatedAs($administrator, 'admin');
        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $guestToken)
            ->get(route('shop.cart.index'))
            ->assertOk();
    }

    public function test_logout_forms_submit_the_current_url_and_route_remains_post_only(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('shop.home'))
            ->assertOk()
            ->assertSee('name="return_to" value="'.route('shop.home').'"', false);

        $this->get(route('customer.logout'))->assertMethodNotAllowed();
    }
}
