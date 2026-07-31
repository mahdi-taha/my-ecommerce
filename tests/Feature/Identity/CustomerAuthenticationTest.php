<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_registered_customer_can_log_in_and_last_login_is_recorded(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'password123',
            'last_login_at' => null,
        ]);

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.account.edit'));

        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertNotNull($customer->fresh()->last_login_at);
    }

    public function test_manual_inactive_and_admin_accounts_cannot_use_customer_login(): void
    {
        $accounts = [
            User::factory()->manualCustomer()->create(['email' => 'manual@example.test', 'password' => 'password123']),
            User::factory()->customer()->inactive()->create(['email' => 'inactive@example.test', 'password' => 'password123']),
            User::factory()->create(['email' => 'admin@example.test', 'password' => 'password123']),
        ];

        foreach ($accounts as $account) {
            $this->post(route('customer.login.store'), [
                'email' => $account->email,
                'password' => 'password123',
            ])->assertSessionHasErrors(['email' => 'The provided credentials are invalid.']);
            $this->assertGuest('customer');
        }
    }

    public function test_customer_logout_clears_only_customer_guard(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'))
            ->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_customer_login_throttles_repeated_failures_using_normalized_email(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'throttled-customer@example.test',
            'password' => 'password123',
        ]);

        foreach (range(1, 5) as $attempt) {
            $email = $attempt % 2 === 0 ? strtoupper($customer->email) : $customer->email;

            $this->post(route('customer.login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => 'The provided credentials are invalid.',
            ]);
        }

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'The provided credentials are invalid.',
        ]);

        $this->assertGuest('customer');
    }

    public function test_successful_customer_login_clears_previous_failures(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'cleared-customer@example.test',
            'password' => 'password123',
        ]);

        $this->failCustomerLogin($customer->email, 4);

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.account.edit'));

        $this->post(route('customer.logout'));
        $this->failCustomerLogin($customer->email, 4);

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.account.edit'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_customer_limiter_is_isolated_from_admin_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'customer-admin-isolation@example.test',
            'password' => 'password123',
        ]);

        $this->failCustomerLogin($admin->email, 5, 'password123');

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    private function failCustomerLogin(string $email, int $attempts, string $password = 'wrong-password'): void
    {
        foreach (range(1, $attempts) as $attempt) {
            $this->post(route('customer.login.store'), compact('email', 'password'))
                ->assertSessionHasErrors([
                    'email' => 'The provided credentials are invalid.',
                ]);
        }
    }
}
