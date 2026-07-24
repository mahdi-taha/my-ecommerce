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
}
