<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Http\Requests\CustomerRegistrationRequest;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_localized_registration_page(): void
    {
        $this->get(route('customer.register'))
            ->assertOk()
            ->assertSee(__('shop.auth.register.title'))
            ->assertSee(route('customer.register.store'), false)
            ->assertSee(route('customer.login'), false);

        app()->setLocale('ar');

        $this->get(route('customer.register'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.auth.register.title'));
    }

    public function test_registration_creates_and_authenticates_a_normal_customer_account(): void
    {
        $response = $this->post(route('customer.register.store'), $this->registrationData([
            'first_name' => '  Jane ',
            'last_name' => ' Doe  ',
            'email' => '  JANE@EXAMPLE.TEST ',
            'phone' => ' 70123456 ',
        ]));

        $response->assertRedirect(route('customer.account.edit'))
            ->assertSessionHas('success', __('shop.auth.register.success'));
        $customer = User::query()->where('email', 'jane@example.test')->firstOrFail();

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertGuest('admin');
        $this->assertSame('Jane Doe', $customer->name);
        $this->assertSame('Jane', $customer->first_name);
        $this->assertSame('Doe', $customer->last_name);
        $this->assertSame('70123456', $customer->phone);
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertTrue($customer->has_account);
        $this->assertTrue($customer->is_active);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNotNull($customer->last_login_at);
        $this->assertTrue(Hash::check('password123', $customer->password));
        $this->assertFalse(Hash::needsRehash($customer->password));
        $this->assertNull($customer->wishlist);
        $this->assertNull($customer->cart);
    }

    public function test_registration_accepts_a_blank_optional_phone(): void
    {
        $this->post(route('customer.register.store'), $this->registrationData([
            'email' => 'blank-phone@example.test',
            'phone' => '   ',
        ]))->assertRedirect(route('customer.account.edit'));

        $this->assertNull(User::query()->where('email', 'blank-phone@example.test')->value('phone'));
    }

    public function test_registration_rejects_duplicate_email_weak_password_and_identity_flags(): void
    {
        User::factory()->manualCustomer()->create(['email' => 'existing@example.test']);

        $this->post(route('customer.register.store'), $this->registrationData([
            'email' => ' EXISTING@EXAMPLE.TEST ',
            'password' => 'short',
            'password_confirmation' => 'different',
            'account_type' => AccountType::Admin->value,
            'has_account' => false,
            'is_active' => false,
        ]))->assertSessionHasErrors([
            'email',
            'password',
            'account_type',
            'has_account',
            'is_active',
        ]);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest('customer');
    }

    public function test_registration_merges_the_guest_cart_and_revalidates_its_coupon(): void
    {
        $tokens = app(GuestCartTokenService::class);
        $rawToken = $tokens->generate();
        $product = $this->eligibleProduct();
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'value' => '5.0000',
        ]);
        $guestCart = Cart::query()->create([
            'guest_token_hash' => $tokens->hash($rawToken),
            'coupon_id' => $coupon->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $guestCart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'registration-cart-item'),
            'quantity' => '2.0000',
        ]);

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $rawToken)
            ->post(route('customer.register.store'), $this->registrationData([
                'email' => 'cart-registration@example.test',
            ]));

        $customer = User::query()->where('email', 'cart-registration@example.test')->firstOrFail();
        $customerCart = $customer->cart()->firstOrFail();

        $response->assertRedirect(route('customer.account.edit'))
            ->assertCookieExpired(GuestCartTokenService::COOKIE_NAME);
        $this->assertModelMissing($guestCart);
        $this->assertSame($coupon->id, $customerCart->coupon_id);
        $this->assertSame('2.0000', $customerCart->items()->firstOrFail()->quantity);
    }

    public function test_registration_is_rate_limited_separately_by_normalized_email_and_ip(): void
    {
        $email = 'limited-registration@example.test';

        foreach (range(1, 5) as $attempt) {
            $this->post(route('customer.register.store'), [
                'email' => $attempt % 2 === 0 ? strtoupper($email) : $email,
            ])->assertSessionHasErrors(['first_name', 'last_name', 'password']);
        }

        $this->post(route('customer.register.store'), $this->registrationData([
            'email' => $email,
        ]))->assertSessionHasErrors([
            'email' => __('shop.auth.register.rate_limited'),
        ]);

        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_successful_registration_clears_its_limiter_and_does_not_affect_login_limiters(): void
    {
        $data = $this->registrationData(['email' => 'clear-registration@example.test']);
        $request = CustomerRegistrationRequest::create(
            route('customer.register.store'),
            'POST',
            $data,
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $request->setContainer($this->app);
        $request->merge(['email' => strtolower(trim($data['email']))]);

        $this->post(route('customer.register.store'), $data)
            ->assertRedirect(route('customer.account.edit'));

        $this->assertSame(0, RateLimiter::attempts($request->throttleKey()));
        $this->post(route('customer.logout'));

        $this->post(route('customer.login.store'), [
            'email' => $data['email'],
            'password' => $data['password'],
        ])->assertRedirect(route('customer.account.edit'));
        $this->assertAuthenticated('customer');
        $this->assertGuest('admin');
    }

    public function test_registration_limiter_is_isolated_from_customer_and_administrator_login(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'registration-customer-isolation@example.test',
            'password' => 'password123',
        ]);
        $administrator = User::factory()->create([
            'email' => 'registration-admin-isolation@example.test',
            'password' => 'password123',
        ]);

        foreach ([$customer, $administrator] as $account) {
            foreach (range(1, 5) as $attempt) {
                $this->post(route('customer.register.store'), [
                    'email' => $account->email,
                ])->assertSessionHasErrors();
            }
        }

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.account.edit'));
        $this->post(route('customer.logout'));

        $this->post(route('admin.login.store'), [
            'email' => $administrator->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertAuthenticatedAs($administrator, 'admin');
    }

    public function test_authenticated_customer_cannot_open_registration(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'customer')
            ->get(route('customer.register'))
            ->assertRedirect('/');
    }

    /** @return array<string, mixed> */
    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Customer',
            'last_name' => 'Example',
            'email' => 'customer-registration@example.test',
            'phone' => null,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    private function eligibleProduct(): Product
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple,
            'status' => true,
            'is_visible_individually' => true,
            'price' => '100.0000',
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Registration Cart Product',
            'url_key' => 'registration-cart-product',
        ]);
        $product->inventory()->create([
            'quantity' => '5.0000',
            'average_cost' => '10.0000',
            'low_stock_alert' => '1.0000',
        ]);

        return $product;
    }
}
