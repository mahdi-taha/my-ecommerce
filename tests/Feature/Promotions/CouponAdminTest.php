<?php

namespace Tests\Feature\Promotions;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_list_create_update_deactivate_and_delete_unused_coupon(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->get(route('admin.coupons.index'))->assertOk();

        $this->post(route('admin.coupons.store'), $this->data(' save-15 ', [
            'type' => CouponType::Percentage->value,
            'value' => '15.0000',
        ]))->assertRedirect(route('admin.coupons.index'));
        $coupon = Coupon::query()->where('code', 'SAVE-15')->firstOrFail();

        $this->put(route('admin.coupons.update', $coupon), $this->data('updated-15'))
            ->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'UPDATED-15']);

        $this->patch(route('admin.coupons.deactivate', $coupon))->assertRedirect();
        $this->assertFalse($coupon->fresh()->is_active);

        $this->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect(route('admin.coupons.index'));
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_validation_rejects_duplicate_invalid_values_limits_and_dates(): void
    {
        $admin = User::factory()->create();
        Coupon::factory()->create(['code' => 'DUPLICATE']);

        $this->actingAs($admin, 'admin')->post(route('admin.coupons.store'), $this->data(' duplicate ', [
            'type' => CouponType::Percentage->value,
            'value' => '100.0001',
            'starts_at' => '2026-07-30 10:00',
            'ends_at' => '2026-07-30 09:00',
            'usage_limit' => 0,
            'per_customer_usage_limit' => -1,
        ]))->assertSessionHasErrors([
            'code', 'value', 'ends_at', 'usage_limit', 'per_customer_usage_limit',
        ]);

        $this->post(route('admin.coupons.store'), $this->data('ZERO', ['value' => '0']))
            ->assertSessionHasErrors('value');
    }

    public function test_used_coupon_cannot_change_code_or_be_deleted_through_admin(): void
    {
        $admin = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'LOCKED']);
        $order = $this->order();
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'coupon_code' => 'LOCKED',
            'coupon_type' => CouponType::Fixed,
            'coupon_value' => '10.0000',
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.coupons.update', $coupon), $this->data('CHANGED'))
            ->assertSessionHasErrors('code');
        $this->delete(route('admin.coupons.destroy', $coupon))
            ->assertSessionHasErrors('coupon');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'LOCKED']);
    }

    public function test_coupon_routes_require_admin_authentication(): void
    {
        $this->get(route('admin.coupons.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('admin.coupons.index'))
            ->assertRedirect(route('admin.login'));
    }

    private function data(string $code, array $overrides = []): array
    {
        return array_merge([
            'code' => $code,
            'name' => 'Admin Coupon',
            'type' => CouponType::Fixed->value,
            'value' => '10.0000',
            'is_active' => '1',
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => null,
            'usage_limit' => null,
            'per_customer_usage_limit' => null,
            'is_first_order_only' => '0',
        ], $overrides);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ORD-ADMIN-COUPON',
            'customer_email' => 'coupon@example.com',
            'customer_first_name' => 'Coupon',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '100.0000',
            'discount_total' => '10.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '90.0000',
            'placed_at' => now(),
        ]);
    }
}
