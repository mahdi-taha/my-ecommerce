<?php

namespace Tests\Feature;

use App\Enums\ShippingMethodType;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\ShippingMethodService;
use Database\Seeders\ShippingMethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_active_store_pickup_without_overwriting_other_edits(): void
    {
        $this->seed(ShippingMethodSeeder::class);

        $this->assertDatabaseHas('shipping_methods', [
            'code' => 'inside_beirut',
            'amount' => '0.0000',
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('shipping_methods', 4);
        $this->assertDatabaseHas('shipping_methods', [
            'code' => 'store_pickup',
            'is_active' => true,
        ]);

        ShippingMethod::where('code', 'inside_beirut')->update([
            'name' => 'Configured Name',
            'amount' => '3.5000',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $this->seed(ShippingMethodSeeder::class);

        $this->assertDatabaseHas('shipping_methods', [
            'code' => 'inside_beirut',
            'name' => 'Configured Name',
            'amount' => '3.5000',
            'is_active' => true,
            'sort_order' => 99,
        ]);
        $this->assertDatabaseCount('shipping_methods', 4);

        ShippingMethod::where('code', 'store_pickup')->update(['is_active' => false]);
        $this->seed(ShippingMethodSeeder::class);
        $this->assertDatabaseHas('shipping_methods', [
            'code' => 'store_pickup',
            'is_active' => true,
        ]);
    }

    public function test_active_scope_orders_by_sort_order_then_id(): void
    {
        $later = ShippingMethod::factory()->create(['is_active' => true, 'sort_order' => 2]);
        $first = ShippingMethod::factory()->create(['is_active' => true, 'sort_order' => 1]);
        $second = ShippingMethod::factory()->create(['is_active' => true, 'sort_order' => 1]);
        ShippingMethod::factory()->create(['is_active' => false, 'sort_order' => 0]);

        $this->assertSame(
            [$first->id, $second->id, $later->id],
            ShippingMethod::activeOrdered()->pluck('id')->all()
        );
    }

    public function test_admin_can_create_edit_and_change_status(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.shipping-methods.store'), [
            'code' => 'local_delivery',
            'name' => 'Local Delivery',
            'type' => ShippingMethodType::Delivery->value,
            'amount' => '2.5000',
            'description' => 'Local area delivery.',
            'is_active' => '1',
            'sort_order' => '3',
        ])->assertRedirect(route('admin.shipping-methods.index'));

        $method = ShippingMethod::where('code', 'local_delivery')->firstOrFail();
        ShippingMethod::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'admin')->put(route('admin.shipping-methods.update', $method), [
            'code' => 'attempted_change',
            'name' => 'Updated Delivery',
            'type' => ShippingMethodType::Pickup->value,
            'amount' => '1.2500',
            'description' => null,
            'is_active' => '1',
            'sort_order' => '4',
        ])->assertRedirect(route('admin.shipping-methods.index'));

        $this->assertDatabaseHas('shipping_methods', [
            'id' => $method->id,
            'code' => 'local_delivery',
            'name' => 'Updated Delivery',
            'amount' => '1.2500',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.shipping-methods.status.update', $method), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse($method->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_the_last_active_shipping_method_from_edit_or_status_action(): void
    {
        $admin = User::factory()->create();
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        $payload = [
            'name' => $method->name,
            'type' => $method->type->value,
            'amount' => $method->amount,
            'description' => $method->description,
            'is_active' => '0',
            'sort_order' => $method->sort_order,
        ];

        $this->actingAs($admin, 'admin')
            ->from(route('admin.shipping-methods.edit', $method))
            ->put(route('admin.shipping-methods.update', $method), $payload)
            ->assertRedirect(route('admin.shipping-methods.edit', $method))
            ->assertSessionHasErrors(['is_active' => 'At least one shipping method must remain active.']);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.shipping-methods.status.update', $method), ['is_active' => '0'])
            ->assertSessionHasErrors(['is_active' => 'At least one shipping method must remain active.']);

        $this->assertTrue($method->fresh()->is_active);
    }

    public function test_admin_can_deactivate_a_shipping_method_when_another_is_active(): void
    {
        $admin = User::factory()->create();
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingMethod::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.shipping-methods.status.update', $method), ['is_active' => '0'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($method->fresh()->is_active);
    }

    public function test_admin_cannot_delete_the_last_active_shipping_method(): void
    {
        $admin = User::factory()->create();
        $method = ShippingMethod::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.shipping-methods.destroy', $method))
            ->assertSessionHasErrors(['is_active' => 'At least one shipping method must remain active.']);

        $this->assertModelExists($method);
    }

    public function test_admin_can_delete_an_active_shipping_method_when_another_is_active(): void
    {
        $admin = User::factory()->create();
        $method = ShippingMethod::factory()->create(['is_active' => true]);
        ShippingMethod::factory()->create(['is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.shipping-methods.destroy', $method))
            ->assertRedirect(route('admin.shipping-methods.index'));

        $this->assertModelMissing($method);
    }

    public function test_admin_can_delete_an_inactive_shipping_method(): void
    {
        $admin = User::factory()->create();
        $method = ShippingMethod::factory()->create(['is_active' => false]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.shipping-methods.destroy', $method))
            ->assertRedirect(route('admin.shipping-methods.index'));

        $this->assertModelMissing($method);
    }

    public function test_invalid_enum_negative_amount_and_duplicate_code_are_rejected(): void
    {
        $admin = User::factory()->create();
        ShippingMethod::factory()->create(['code' => 'duplicate']);

        $this->actingAs($admin, 'admin')->post(route('admin.shipping-methods.store'), [
            'code' => 'duplicate',
            'name' => 'Invalid',
            'type' => 'invalid',
            'amount' => '-0.0001',
            'is_active' => '1',
            'sort_order' => '0',
        ])->assertSessionHasErrors(['code', 'type', 'amount']);
    }

    public function test_update_request_does_not_accept_code_changes(): void
    {
        $method = ShippingMethod::factory()->create(['code' => 'immutable_code']);

        app(ShippingMethodService::class)->update($method, [
            'code' => 'changed_code',
            'name' => 'Changed Name',
            'amount' => '5.0000',
        ]);

        $this->assertSame('immutable_code', $method->fresh()->code);
    }

    public function test_shipping_routes_require_an_active_admin(): void
    {
        $this->get(route('admin.shipping-methods.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('admin.shipping-methods.index'))
            ->assertRedirect(route('admin.login'));
    }
}
