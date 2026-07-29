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
        $customer = User::factory()->customer()->create([
            'name' => 'Original Display',
            'email' => 'original@example.test',
            'password' => 'original123',
        ]);
        $password = $customer->password;

        $this->actingAs($customer, 'customer')->put(route('customer.account.update'), [
            'first_name' => 'First',
            'last_name' => 'Last',
            'phone' => '   ',
        ])->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Original Display', $customer->name);
        $this->assertSame('original@example.test', $customer->email);
        $this->assertSame('First', $customer->first_name);
        $this->assertSame('Last', $customer->last_name);
        $this->assertNull($customer->phone);
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertSame($password, $customer->password);
    }

    public function test_profile_rejects_protected_fields(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'customer')->put(route('customer.account.update'), [
            'first_name' => 'First',
            'last_name' => 'Last',
            'phone' => '70123456',
            'name' => 'Changed',
            'email' => 'changed@example.test',
            'account_type' => AccountType::Admin->value,
            'is_active' => false,
        ])->assertSessionHasErrors(['name', 'email', 'account_type', 'is_active']);

        $customer->refresh();
        $this->assertNotSame('Changed', $customer->name);
        $this->assertNotSame('changed@example.test', $customer->email);
        $this->assertSame(AccountType::Customer, $customer->account_type);
        $this->assertTrue($customer->is_active);
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
