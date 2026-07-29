<?php

namespace Tests\Feature\Promotions;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CouponUsageRelease;
use App\Models\Order;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class CouponFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_schema_and_enum_match_the_approved_foundation(): void
    {
        $this->assertTrue(Schema::hasColumns('coupons', [
            'code', 'name', 'type', 'value', 'is_active', 'starts_at', 'ends_at',
            'minimum_subtotal', 'usage_limit', 'per_customer_usage_limit',
            'is_first_order_only',
        ]));
        $this->assertTrue(Schema::hasColumns('coupon_usages', [
            'coupon_id', 'order_id', 'user_id', 'coupon_code', 'coupon_type',
            'coupon_value', 'eligible_subtotal', 'discount_amount',
        ]));
        $this->assertTrue(Schema::hasColumns('coupon_usage_releases', [
            'coupon_usage_id', 'reason', 'released_at',
        ]));
        $this->assertSame(['fixed', 'percentage'], array_column(CouponType::cases(), 'value'));
    }

    public function test_usage_relationships_and_snapshot_casts_are_available(): void
    {
        $coupon = Coupon::factory()->create(['type' => CouponType::Percentage]);
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $usage = CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'coupon_code' => $coupon->code,
            'coupon_type' => CouponType::Percentage,
            'coupon_value' => '10.0000',
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]);
        $release = CouponUsageRelease::create([
            'coupon_usage_id' => $usage->id,
            'reason' => 'order_cancelled',
            'released_at' => now(),
        ]);

        $this->assertTrue($usage->coupon->is($coupon));
        $this->assertTrue($usage->order->is($order));
        $this->assertTrue($usage->user->is($customer));
        $this->assertTrue($usage->release->is($release));
        $this->assertTrue($release->usage->is($usage));
        $this->assertSame(CouponType::Percentage, $usage->coupon_type);
        $this->assertSame('10.0000', $usage->discount_amount);
        $this->assertSame(0, $coupon->unreleasedUsages()->count());
    }

    public function test_usage_and_release_records_are_immutable_and_append_only(): void
    {
        [$usage, $release] = $this->usageWithRelease();

        try {
            $usage->update(['discount_amount' => '1.0000']);
            $this->fail('CouponUsage update was not rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Coupon usage records are immutable.', $exception->getMessage());
        }

        try {
            $release->delete();
            $this->fail('CouponUsageRelease deletion was not rejected.');
        } catch (LogicException $exception) {
            $this->assertSame('Coupon usage releases are append-only.', $exception->getMessage());
        }

        $this->assertDatabaseHas('coupon_usages', ['id' => $usage->id]);
        $this->assertDatabaseHas('coupon_usage_releases', ['id' => $release->id]);
    }

    public function test_database_enforces_one_usage_per_order_and_one_release_per_usage(): void
    {
        [$usage] = $this->usageWithRelease();

        try {
            CouponUsage::create([
                'coupon_id' => Coupon::factory()->create()->id,
                'order_id' => $usage->order_id,
                'coupon_code' => 'SECOND',
                'coupon_type' => CouponType::Fixed,
                'coupon_value' => '1.0000',
                'eligible_subtotal' => '10.0000',
                'discount_amount' => '1.0000',
            ]);
            $this->fail('A second Coupon usage was created for one Order.');
        } catch (QueryException) {
            $this->assertDatabaseCount('coupon_usages', 1);
        }

        try {
            CouponUsageRelease::create([
                'coupon_usage_id' => $usage->id,
                'reason' => 'duplicate',
                'released_at' => now(),
            ]);
            $this->fail('A second release was created for one Coupon usage.');
        } catch (QueryException) {
            $this->assertDatabaseCount('coupon_usage_releases', 1);
        }
    }

    public function test_coupon_service_normalizes_codes_and_allows_unused_deletion(): void
    {
        $service = app(CouponService::class);
        $coupon = $service->create($this->couponData(code: '  save-10  '));

        $this->assertSame('SAVE-10', $coupon->code);
        $service->update($coupon, $this->couponData(code: ' revised_10 '));
        $this->assertSame('REVISED_10', $coupon->fresh()->code);

        $service->deleteUnused($coupon);
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_used_coupon_code_and_deletion_are_rejected_but_deactivation_is_allowed(): void
    {
        $service = app(CouponService::class);
        $coupon = Coupon::factory()->create(['code' => 'LOCKED', 'is_active' => true]);
        $this->usage($coupon);

        try {
            $service->update($coupon, $this->couponData(code: 'CHANGED'));
            $this->fail('Used Coupon code change was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }

        try {
            $service->deleteUnused($coupon);
            $this->fail('Used Coupon deletion was not rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon', $exception->errors());
        }

        $this->assertFalse($service->deactivate($coupon)->is_active);
        $this->assertDatabaseHas('coupon_usages', ['coupon_id' => $coupon->id]);
    }

    private function usageWithRelease(): array
    {
        $usage = $this->usage(Coupon::factory()->create());
        $release = CouponUsageRelease::create([
            'coupon_usage_id' => $usage->id,
            'reason' => 'order_cancelled',
            'released_at' => now(),
        ]);

        return [$usage, $release];
    }

    private function usage(Coupon $coupon): CouponUsage
    {
        $order = $this->order();

        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'coupon_code' => $coupon->code,
            'coupon_type' => $coupon->type,
            'coupon_value' => $coupon->value,
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]);
    }

    private function order(?User $customer = null): Order
    {
        return Order::create([
            'order_number' => 'ORD-TEST-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'guest@example.com',
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

    private function couponData(string $code): array
    {
        return [
            'code' => $code,
            'name' => 'Test Coupon',
            'type' => CouponType::Fixed->value,
            'value' => '10.0000',
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'minimum_subtotal' => null,
            'usage_limit' => null,
            'per_customer_usage_limit' => null,
            'is_first_order_only' => false,
        ];
    }
}
