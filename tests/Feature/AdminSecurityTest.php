<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_log_in(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'password123',
        ]);

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('admin.products.index'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_customer_credentials_are_rejected(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'password123',
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ]);

        $response
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'email' => 'The provided credentials are invalid.',
            ]);
        $this->assertGuest('admin');
    }

    public function test_inactive_admin_credentials_are_rejected(): void
    {
        $admin = User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => 'password123',
            'is_active' => false,
        ]);

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors([
                'email' => 'The provided credentials are invalid.',
            ]);
        $this->assertGuest('admin');
    }

    public function test_admin_without_an_account_is_rejected(): void
    {
        $admin = User::factory()->create([
            'email' => 'manual-admin@example.test',
            'password' => 'password123',
            'has_account' => false,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertSessionHasErrors(['email' => 'The provided credentials are invalid.']);

        $this->assertGuest('admin');
    }

    public function test_unauthenticated_user_is_redirected_by_auth_middleware(): void
    {
        $this->get(route('admin.products.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_customer_receives_forbidden_on_admin_routes(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'admin')
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_inactive_authenticated_admin_receives_forbidden(): void
    {
        $admin = User::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.index'))
            ->assertForbidden();
    }

    public function test_active_authenticated_admin_can_access_admin_routes(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.products.index'))
            ->assertOk();
    }

    public function test_last_login_at_is_updated_after_successful_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'last-login@example.test',
            'password' => 'password123',
            'last_login_at' => null,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_failed_login_does_not_update_last_login_at(): void
    {
        $admin = User::factory()->create([
            'email' => 'failed-login@example.test',
            'password' => 'password123',
            'last_login_at' => null,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $this->assertNull($admin->fresh()->last_login_at);
    }

    public function test_admin_login_throttles_repeated_failures_using_normalized_email(): void
    {
        $admin = User::factory()->create([
            'email' => 'throttled-admin@example.test',
            'password' => 'password123',
        ]);

        foreach (range(1, 5) as $attempt) {
            $email = $attempt % 2 === 0 ? strtoupper($admin->email) : $admin->email;

            $this->post(route('admin.login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => 'The provided credentials are invalid.',
            ]);
        }

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertSessionHasErrors([
            'email' => 'The provided credentials are invalid.',
        ]);

        $this->assertGuest('admin');
    }

    public function test_successful_admin_login_clears_previous_failures(): void
    {
        $admin = User::factory()->create([
            'email' => 'cleared-admin@example.test',
            'password' => 'password123',
        ]);

        $this->failAdminLogin($admin->email, 4);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.products.index'));

        $this->post(route('admin.logout'));
        $this->failAdminLogin($admin->email, 4);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_admin_limiter_is_isolated_from_customer_login(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'admin-customer-isolation@example.test',
            'password' => 'password123',
        ]);

        $this->failAdminLogin($customer->email, 5, 'password123');

        $this->post(route('customer.login.store'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.account.edit'));

        $this->assertAuthenticatedAs($customer, 'customer');
    }

    private function failAdminLogin(string $email, int $attempts, string $password = 'wrong-password'): void
    {
        foreach (range(1, $attempts) as $attempt) {
            $this->post(route('admin.login.store'), compact('email', 'password'))
                ->assertSessionHasErrors([
                    'email' => 'The provided credentials are invalid.',
                ]);
        }
    }
}
