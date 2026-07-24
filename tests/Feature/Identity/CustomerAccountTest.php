<?php

namespace Tests\Feature\Identity;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_profile_without_changing_protected_fields(): void
    {
        $customer = User::factory()->customer()->create(['password' => 'original123']);
        $password = $customer->password;

        $this->actingAs($customer, 'customer')->put(route('customer.account.update'), [
            'name' => 'Store Display',
            'first_name' => 'First',
            'last_name' => 'Last',
            'email' => 'profile@example.test',
            'phone' => '   ',
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Store Display', $customer->name);
        $this->assertNull($customer->phone);
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertSame($password, $customer->password);
    }

    public function test_customer_can_change_password_with_current_password(): void
    {
        $customer = User::factory()->customer()->create(['password' => 'original123']);

        $this->actingAs($customer, 'customer')->put(route('customer.account.password.update'), [
            'current_password' => 'original123',
            'password' => 'replacement123',
            'password_confirmation' => 'replacement123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('replacement123', $customer->fresh()->password));
    }

    public function test_manual_customer_cannot_access_customer_account(): void
    {
        $manual = User::factory()->manualCustomer()->create();
        $this->actingAs($manual, 'customer')->get(route('customer.account.edit'))->assertForbidden();
    }
}
