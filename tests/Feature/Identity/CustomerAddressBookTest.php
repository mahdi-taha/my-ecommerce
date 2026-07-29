<?php

namespace Tests\Feature\Identity;

use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CustomerAddressBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_normalized_address_without_implicit_defaults(): void
    {
        $customer = User::factory()->customer()->create();
        $data = $this->addressData([
            'label' => '  Home  ',
            'country_code' => 'lb',
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('customer.addresses.store'), $data)
            ->assertRedirect(route('customer.addresses.index'));

        $this->assertDatabaseHas('customer_addresses', [
            'user_id' => $customer->id,
            'label' => 'Home',
            'country_code' => 'LB',
            'state' => 'Beirut',
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ]);
    }

    public function test_customer_can_update_address_and_make_it_both_defaults(): void
    {
        $customer = User::factory()->customer()->create();
        $first = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $second = CustomerAddress::factory()->for($customer, 'customer')->create();

        $this->actingAs($customer, 'customer')->put(
            route('customer.addresses.update', $second),
            $this->addressData([
                'label' => 'Office',
                'is_default_shipping' => true,
                'is_default_billing' => true,
            ])
        )->assertRedirect(route('customer.addresses.index'));

        $this->assertFalse($first->fresh()->is_default_shipping);
        $this->assertFalse($first->fresh()->is_default_billing);
        $this->assertTrue($second->fresh()->is_default_shipping);
        $this->assertTrue($second->fresh()->is_default_billing);
        $this->assertSame('Office', $second->fresh()->label);
    }

    public function test_shipping_and_billing_defaults_remain_independent_and_unique(): void
    {
        $customer = User::factory()->customer()->create();
        $first = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $second = CustomerAddress::factory()->for($customer, 'customer')->create();

        $this->actingAs($customer, 'customer')
            ->patch(route('customer.addresses.default-shipping', $second));

        $this->assertFalse($first->fresh()->is_default_shipping);
        $this->assertTrue($first->fresh()->is_default_billing);
        $this->assertTrue($second->fresh()->is_default_shipping);
        $this->assertFalse($second->fresh()->is_default_billing);

        $this->patch(route('customer.addresses.default-billing', $second));
        $this->assertSame(1, CustomerAddress::where('user_id', $customer->id)
            ->where('is_default_shipping', true)->count());
        $this->assertSame(1, CustomerAddress::where('user_id', $customer->id)
            ->where('is_default_billing', true)->count());
        $this->assertTrue($second->fresh()->is_default_billing);
    }

    public function test_deleting_defaults_reassigns_roles_to_oldest_remaining_address(): void
    {
        $customer = User::factory()->customer()->create();
        $deleted = CustomerAddress::factory()->for($customer, 'customer')->create([
            'created_at' => now()->subDays(3),
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        $oldestRemaining = CustomerAddress::factory()->for($customer, 'customer')->create([
            'created_at' => now()->subDays(2),
        ]);
        CustomerAddress::factory()->for($customer, 'customer')->create([
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($customer, 'customer')
            ->delete(route('customer.addresses.destroy', $deleted));

        $this->assertTrue($oldestRemaining->fresh()->is_default_shipping);
        $this->assertTrue($oldestRemaining->fresh()->is_default_billing);
    }

    public function test_deleting_one_default_preserves_other_role_and_final_delete_leaves_none(): void
    {
        $customer = User::factory()->customer()->create();
        $shipping = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_shipping' => true,
        ]);
        $billing = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_billing' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->delete(route('customer.addresses.destroy', $shipping));

        $billing->refresh();
        $this->assertTrue($billing->is_default_shipping);
        $this->assertTrue($billing->is_default_billing);

        $this->delete(route('customer.addresses.destroy', $billing));
        $this->assertDatabaseMissing('customer_addresses', ['user_id' => $customer->id]);
        $this->assertNull($customer->defaultShippingAddress()->first());
        $this->assertNull($customer->defaultBillingAddress()->first());
    }

    public function test_address_routes_enforce_authentication_ownership_and_validation(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $address = CustomerAddress::factory()->for($owner, 'customer')->create();

        $this->get(route('customer.addresses.index'))->assertRedirect(route('customer.login'));

        $this->actingAs($other, 'customer')
            ->get(route('customer.addresses.edit', $address))->assertNotFound();
        $this->put(route('customer.addresses.update', $address), $this->addressData())
            ->assertNotFound();
        $this->delete(route('customer.addresses.destroy', $address))->assertNotFound();
        $this->patch(route('customer.addresses.default-shipping', $address))->assertNotFound();
        $this->assertDatabaseHas('customer_addresses', ['id' => $address->id]);

        $this->actingAs($owner, 'customer')->post(route('customer.addresses.store'), [
            'user_id' => $other->id,
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'country_code' => 'LBN',
            'state' => '',
            'city' => '',
            'address_line_1' => '',
        ])->assertSessionHasErrors([
            'user_id', 'first_name', 'last_name', 'phone', 'country_code',
            'state', 'city', 'address_line_1',
        ]);
    }

    public function test_saved_address_changes_do_not_modify_order_snapshots_or_other_domains(): void
    {
        $customer = User::factory()->customer()->create();
        $saved = CustomerAddress::factory()->for($customer, 'customer')->create();
        $order = $this->order($customer);
        $snapshot = $order->addresses()->create([
            'type' => 'billing',
            'first_name' => 'Historical',
            'last_name' => 'Snapshot',
            'company' => null,
            'email' => 'snapshot@example.test',
            'phone' => '70111111',
            'address_line_1' => 'Historical Street',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => 'Beirut',
            'postal_code' => null,
            'country_code' => 'LB',
        ]);
        $before = [Cart::count(), Order::count()];

        $this->actingAs($customer, 'customer')->put(
            route('customer.addresses.update', $saved),
            $this->addressData(['address_line_1' => 'Changed Saved Street'])
        );

        $this->assertSame('Historical Street', $snapshot->fresh()->address_line_1);
        $this->assertSame($before, [Cart::count(), Order::count()]);
    }

    public function test_address_index_lists_all_customer_addresses_without_cross_customer_data(): void
    {
        $customer = User::factory()->customer()->create();
        $own = CustomerAddress::factory()->for($customer, 'customer')->create(['label' => 'Own Address']);
        $foreign = CustomerAddress::factory()->create(['label' => 'Foreign Address']);

        $this->actingAs($customer, 'customer')->get(route('customer.addresses.index'))
            ->assertOk()
            ->assertSee($own->label)
            ->assertDontSee($foreign->label)
            ->assertViewHas('addresses', fn ($addresses) => $addresses->count() === 1 && $addresses->first()->is($own));
    }

    public function test_migration_preflight_reports_legacy_null_address_fields(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('state')->nullable()->change();
        });

        $customer = User::factory()->customer()->create();
        $id = DB::table('customer_addresses')->insertGetId([
            'user_id' => $customer->id,
            'first_name' => 'Legacy',
            'last_name' => 'Address',
            'phone' => null,
            'address_line_1' => 'Legacy Street',
            'city' => 'Beirut',
            'state' => null,
            'country_code' => 'LB',
            'is_default_shipping' => false,
            'is_default_billing' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $migration = require database_path('migrations/2026_07_29_000003_align_customer_addresses_for_address_book.php');
            $migration->up();
            $this->fail('The migration accepted incomplete legacy address data.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString((string) $id, $exception->getMessage());
            $this->assertStringContainsString('phone and Governorate', $exception->getMessage());
        } finally {
            DB::table('customer_addresses')->where('id', $id)->delete();
            Schema::table('customer_addresses', function (Blueprint $table) {
                $table->string('phone')->nullable(false)->change();
                $table->string('state')->nullable(false)->change();
            });
        }
    }

    public function test_migration_backfills_legacy_default_into_both_roles(): void
    {
        $migration = require database_path('migrations/2026_07_29_000003_align_customer_addresses_for_address_book.php');
        $migration->down();
        $customer = User::factory()->customer()->create();
        $id = DB::table('customer_addresses')->insertGetId([
            'user_id' => $customer->id,
            'first_name' => 'Legacy',
            'last_name' => 'Default',
            'phone' => '70123456',
            'address_line_1' => 'Legacy Street',
            'city' => 'Beirut',
            'state' => 'Beirut',
            'country_code' => 'LB',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('customer_addresses', [
            'id' => $id,
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
    }

    private function addressData(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'first_name' => 'Jane',
            'last_name' => 'Customer',
            'company' => null,
            'phone' => '70123456',
            'country_code' => 'LB',
            'state' => 'Beirut',
            'city' => 'Beirut',
            'address_line_1' => 'Main Street',
            'address_line_2' => null,
            'postal_code' => null,
            'is_default_shipping' => false,
            'is_default_billing' => false,
        ], $overrides);
    }

    private function order(User $customer): Order
    {
        return Order::create([
            'order_number' => 'ORD-2026-900001',
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_first_name' => 'Historical',
            'customer_last_name' => 'Customer',
            'customer_phone' => '70111111',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ]);
    }
}
