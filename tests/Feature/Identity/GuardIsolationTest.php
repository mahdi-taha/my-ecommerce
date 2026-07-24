<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_customer_sessions_are_isolated(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin, 'admin');
        $this->actingAs($customer, 'customer');

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get(route('admin.customers.index'))->assertOk();
        $this->get(route('customer.account.edit'))->assertOk();

        $this->post(route('customer.logout'))->assertRedirect(route('customer.login'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('customer');
    }

    public function test_admin_logout_does_not_end_customer_session(): void
    {
        $admin = User::factory()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin, 'admin');
        $this->actingAs($customer, 'customer');

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
        $this->assertAuthenticatedAs($customer, 'customer');
    }
}
