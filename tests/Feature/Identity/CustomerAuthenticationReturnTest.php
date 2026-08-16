<?php

namespace Tests\Feature\Identity;

use App\Models\Cart;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthenticationReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_to_home_and_removes_the_stored_destination(): void
    {
        $customer = $this->customer();

        $this->get(route('customer.login', ['return_to' => route('shop.home')]))
            ->assertOk()
            ->assertSessionHas('customer_return_to', route('shop.home'));

        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect(route('shop.home'))
            ->assertSessionMissing('customer_return_to');
    }

    public function test_login_failures_preserve_the_product_destination_until_success(): void
    {
        $customer = $this->customer();
        $destination = route('shop.products.show', ['url_key' => 'camera']);
        $this->get(route('customer.login', ['return_to' => $destination]));

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'incorrect',
        ])->assertSessionHasErrors('email')
            ->assertSessionHas('customer_return_to', $destination);

        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect($destination)
            ->assertSessionMissing('customer_return_to');
    }

    public function test_checkout_login_returns_to_checkout_and_failed_login_preserves_it(): void
    {
        $customer = $this->customer();
        $destination = route('shop.checkout.show');

        $this->get(route('customer.login', ['return_to' => $destination]))
            ->assertSessionHas('customer_return_to', $destination);

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'incorrect',
        ])->assertSessionHasErrors('email')
            ->assertSessionHas('customer_return_to', $destination);

        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect($destination)
            ->assertSessionMissing('customer_return_to');
    }

    public function test_registration_validation_failure_preserves_cart_destination_and_merge_completes_first(): void
    {
        $tokens = app(GuestCartTokenService::class);
        $guestToken = $tokens->generate();
        $guestCart = Cart::query()->create([
            'guest_token_hash' => $tokens->hash($guestToken),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $destination = route('shop.cart.index');

        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $guestToken)
            ->get(route('customer.register', ['return_to' => $destination]));
        $this->post(route('customer.register.store'), ['email' => 'new@example.test'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'password'])
            ->assertSessionHas('customer_return_to', $destination);

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $guestToken)
            ->post(route('customer.register.store'), $this->registrationData());

        $customer = User::query()->where('email', 'new@example.test')->firstOrFail();
        $response->assertRedirect($destination)
            ->assertCookieExpired(GuestCartTokenService::COOKIE_NAME)
            ->assertSessionMissing('customer_return_to');
        $this->assertModelMissing($guestCart);
        $this->assertNotNull($customer->cart);
    }

    public function test_checkout_registration_returns_to_checkout_and_validation_failure_preserves_it(): void
    {
        $destination = route('shop.checkout.show');

        $this->get(route('customer.register', ['return_to' => $destination]))
            ->assertSessionHas('customer_return_to', $destination);

        $this->post(route('customer.register.store'), ['email' => 'new@example.test'])
            ->assertSessionHasErrors(['first_name', 'last_name', 'password'])
            ->assertSessionHas('customer_return_to', $destination);

        $this->post(route('customer.register.store'), $this->registrationData())
            ->assertRedirect($destination)
            ->assertSessionMissing('customer_return_to');
    }

    public function test_checkout_return_requires_a_same_origin_localized_url(): void
    {
        foreach ([
            'https://example.com/en/checkout',
            url('/checkout'),
        ] as $invalid) {
            $this->get(route('customer.login', ['return_to' => $invalid]))
                ->assertSessionMissing('customer_return_to');
        }
    }

    public function test_direct_login_and_registration_fall_back_to_customer_profile(): void
    {
        $customer = $this->customer();

        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect(route('customer.account.edit'));

        $this->post(route('customer.logout'));

        $data = $this->registrationData();
        $data['email'] = 'another-new@example.test';

        $this->post(route('customer.register.store'), $data)
            ->assertRedirect(route('customer.account.edit'));
    }

    public function test_invalid_queries_do_not_replace_an_existing_valid_destination(): void
    {
        $destination = route('shop.cart.index');

        foreach ([
            'https://example.com/account',
            '//example.com/account',
            route('customer.account.edit'),
            route('customer.login'),
            url('/unknown'),
            'not a url',
        ] as $invalid) {
            $this->withSession(['customer_return_to' => $destination])
                ->get(route('customer.login', ['return_to' => $invalid]))
                ->assertOk()
                ->assertSessionHas('customer_return_to', $destination);
        }
    }

    public function test_valid_public_destination_takes_priority_and_clears_stale_intended_url(): void
    {
        $customer = $this->customer();

        $this->withSession([
            'customer_return_to' => route('shop.home'),
            'url.intended' => route('customer.account.edit'),
        ])->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect(route('shop.home'))
            ->assertSessionMissing('customer_return_to')
            ->assertSessionMissing('url.intended');
    }

    public function test_login_accepts_a_localized_category_as_a_public_return_destination(): void
    {
        $customer = $this->customer();
        $category = Category::factory()->create();
        $category->translations()->create(['locale' => 'en', 'name' => 'Audio', 'slug' => 'audio']);
        $destination = route('shop.categories.show', 'audio');

        $this->get(route('customer.login', ['return_to' => $destination]))
            ->assertSessionHas('customer_return_to', $destination);
        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect($destination)
            ->assertSessionMissing('customer_return_to');
    }

    public function test_login_accepts_an_active_cms_page_as_a_public_return_destination(): void
    {
        $customer = $this->customer();
        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->create([
            'locale' => 'en',
            'title' => 'About Us',
            'slug' => 'about-us',
            'body' => 'About the store.',
        ]);
        $destination = route('shop.pages.show', 'about-us');

        $this->get(route('customer.login', ['return_to' => $destination]))
            ->assertSessionHas('customer_return_to', $destination);
        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect($destination)
            ->assertSessionMissing('customer_return_to');
    }

    public function test_protected_route_intended_redirect_is_preserved_without_public_destination(): void
    {
        $customer = $this->customer();

        $this->get(route('shop.account.orders.index'))
            ->assertRedirect(route('customer.login'));

        $this->post(route('customer.login.store'), $this->credentials($customer))
            ->assertRedirect(route('shop.account.orders.index'));
    }

    private function customer(): User
    {
        return User::factory()->customer()->create([
            'email' => 'returning@example.test',
            'password' => 'password123',
        ]);
    }

    /** @return array<string, string> */
    private function credentials(User $customer): array
    {
        return ['email' => $customer->email, 'password' => 'password123'];
    }

    /** @return array<string, string> */
    private function registrationData(): array
    {
        return [
            'first_name' => 'New',
            'last_name' => 'Customer',
            'email' => 'new@example.test',
            'phone' => '',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ];
    }
}
