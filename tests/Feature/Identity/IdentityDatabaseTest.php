<?php

namespace Tests\Feature\Identity;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_customers_support_nullable_credentials_and_shared_phones(): void
    {
        User::factory()->manualCustomer()->create(['phone' => '+96170000000']);
        $customer = User::factory()->manualCustomer()->create(['phone' => '+96170000000']);

        $this->assertNull($customer->email);
        $this->assertNull($customer->password);
        $this->assertFalse($customer->has_account);
        $this->assertCount(2, User::customers()->where('phone', '+96170000000')->get());
    }

    public function test_customer_addresses_are_deleted_with_customer(): void
    {
        $customer = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->for($customer, 'customer')->create();

        $customer->delete();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_customer_exposes_independent_default_address_relationships(): void
    {
        $customer = User::factory()->customer()->create();
        $shipping = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_shipping' => true,
        ]);
        $billing = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_billing' => true,
        ]);

        $this->assertTrue($customer->defaultShippingAddress()->is($shipping));
        $this->assertTrue($customer->defaultBillingAddress()->is($billing));
        $this->assertTrue($customer->defaultAddress()->is($shipping));
    }
}
