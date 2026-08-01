<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Notifications\CustomerResetPasswordNotification;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_password_pages_are_localized_and_use_customer_routes(): void
    {
        $this->get(route('customer.password.request'))
            ->assertOk()
            ->assertSee(__('shop.auth.password.forgot_title'))
            ->assertSee(route('customer.password.email'), false);

        app()->setLocale('ar');

        $this->get(route('customer.password.reset', ['token' => 'token', 'email' => 'test@example.test']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.auth.password.reset_title'))
            ->assertSee(route('customer.password.store'), false);
    }

    public function test_only_eligible_customers_receive_the_localized_reset_notification(): void
    {
        Notification::fake();
        $eligible = User::factory()->customer()->create(['email' => 'eligible@example.test']);
        $accounts = [
            User::factory()->customer()->inactive()->create(['email' => 'inactive-reset@example.test']),
            User::factory()->manualCustomer()->create(['email' => 'manual-reset@example.test']),
            User::factory()->create(['email' => 'admin-reset@example.test']),
        ];

        foreach ([$eligible, ...$accounts] as $account) {
            $this->post(route('customer.password.email'), ['email' => $account->email])
                ->assertSessionHas('status', __('shop.auth.password.generic_response'));
        }
        $this->post(route('customer.password.email'), ['email' => 'unknown@example.test'])
            ->assertSessionHas('status', __('shop.auth.password.generic_response'));

        Notification::assertSentTo($eligible, CustomerResetPasswordNotification::class, function ($notification): bool {
            return $notification->locale === 'en';
        });
        Notification::assertNotSentTo($accounts, CustomerResetPasswordNotification::class);
    }

    public function test_notification_failure_does_not_change_the_generic_browser_response(): void
    {
        $customer = User::factory()->customer()->create(['email' => 'mail-failure@example.test']);
        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Simulated mail failure.'));

        $this->post(route('customer.password.email'), ['email' => $customer->email])
            ->assertSessionHas('status', __('shop.auth.password.generic_response'))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_valid_token_resets_password_once_without_logging_in_or_changing_last_login(): void
    {
        $lastLogin = now()->subDay()->startOfSecond();
        $customer = User::factory()->customer()->create([
            'email' => 'reset@example.test',
            'password' => 'old-password1',
            'remember_token' => 'old-remember-token',
            'last_login_at' => $lastLogin,
        ]);
        $token = Password::broker('customers')->createToken($customer);

        $this->post(route('customer.password.store'), $this->resetData($customer->email, $token))
            ->assertRedirect(route('customer.login'))
            ->assertSessionHas('status', __('shop.auth.password.reset_success'));

        $customer->refresh();
        $this->assertTrue(Hash::check('new-password1', $customer->password));
        $this->assertNotSame('old-remember-token', $customer->remember_token);
        $this->assertTrue($lastLogin->equalTo($customer->last_login_at));
        $this->assertGuest('customer');

        $this->post(route('customer.password.store'), $this->resetData($customer->email, $token, 'other-password1'))
            ->assertSessionHasErrors([
                'email' => __('shop.auth.password.reset_failed'),
            ]);
        $this->assertTrue(Hash::check('new-password1', $customer->fresh()->password));
    }

    public function test_reset_revalidates_customer_eligibility(): void
    {
        foreach ([
            User::factory()->customer()->inactive()->create(['email' => 'inactive-token@example.test']),
            User::factory()->manualCustomer()->create(['email' => 'manual-token@example.test']),
            User::factory()->create(['email' => 'admin-token@example.test']),
        ] as $account) {
            $token = Password::broker('customers')->createToken($account);

            $this->post(route('customer.password.store'), $this->resetData($account->email, $token))
                ->assertSessionHasErrors([
                    'email' => __('shop.auth.password.reset_failed'),
                ]);
        }
    }

    public function test_expired_or_invalid_tokens_are_rejected(): void
    {
        $customer = User::factory()->customer()->create(['email' => 'expired@example.test']);

        $this->post(route('customer.password.store'), $this->resetData($customer->email, 'invalid-token'))
            ->assertSessionHasErrors(['email' => __('shop.auth.password.reset_failed')]);

        $token = Password::broker('customers')->createToken($customer);
        $this->travel(61)->minutes();

        $this->post(route('customer.password.store'), $this->resetData($customer->email, $token))
            ->assertSessionHasErrors(['email' => __('shop.auth.password.reset_failed')]);
    }

    public function test_forgot_and_reset_limiters_are_normalized_and_isolated(): void
    {
        $email = 'rate-limit@example.test';

        foreach (range(1, 5) as $attempt) {
            $this->post(route('customer.password.email'), [
                'email' => $attempt % 2 === 0 ? ' RATE-LIMIT@EXAMPLE.TEST ' : $email,
            ])->assertSessionHas('status', __('shop.auth.password.generic_response'));
        }

        $this->post(route('customer.password.email'), ['email' => $email])
            ->assertSessionHasErrors(['email' => __('shop.auth.password.generic_response')]);

        $this->post(route('customer.password.store'), $this->resetData($email, 'invalid-token'))
            ->assertSessionHasErrors(['email' => __('shop.auth.password.reset_failed')]);

        $customer = User::factory()->customer()->create([
            'email' => $email,
            'password' => 'current-password1',
        ]);
        $this->post(route('customer.login.store'), [
            'email' => $email,
            'password' => 'current-password1',
        ])->assertRedirect(route('customer.account.edit'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_successful_reset_preserves_admin_authentication_and_guest_cart_cookie(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->customer()->create(['email' => 'isolated-reset@example.test']);
        $token = Password::broker('customers')->createToken($customer);
        $guestToken = 'guest-cart-token-that-remains-client-owned';

        $response = $this->actingAs($admin, 'admin')
            ->withCookie(GuestCartTokenService::COOKIE_NAME, $guestToken)
            ->post(route('customer.password.store'), $this->resetData($customer->email, $token));

        $response->assertRedirect(route('customer.login'));
        $this->assertFalse(collect($response->headers->getCookies())->contains(
            fn ($cookie): bool => $cookie->getName() === GuestCartTokenService::COOKIE_NAME
        ));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }

    /** @return array<string, string> */
    private function resetData(string $email, string $token, string $password = 'new-password1'): array
    {
        return [
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
