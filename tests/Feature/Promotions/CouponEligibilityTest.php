<?php

namespace Tests\Feature\Promotions;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CouponUsageRelease;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\CouponEligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::query()->updateOrCreate(
            ['group' => 'localization', 'key' => 'timezone'],
            ['value' => 'Asia/Beirut', 'type' => 'text']
        );
        cache()->forget('setting.localization.timezone');
    }

    public function test_active_coupon_respects_inclusive_start_exclusive_end_and_minimum_boundary(): void
    {
        $service = app(CouponEligibilityService::class);
        $at = CarbonImmutable::parse('2026-07-29 12:00:00', 'Asia/Beirut');
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'starts_at' => $at->utc(),
            'ends_at' => $at->addHour()->utc(),
            'minimum_subtotal' => '100.0000',
        ]);

        $this->assertSame([], $service->validate($coupon, '100.0000', null, $at));
        $this->assertContains('coupon_minimum_not_met', $service->validate($coupon, '99.9999', null, $at));
        $this->assertContains('coupon_expired', $service->validate($coupon, '100.0000', null, $at->addHour()));
        $this->assertContains('coupon_not_started', $service->validate($coupon, '100.0000', null, $at->subSecond()));
    }

    public function test_null_dates_and_limits_are_unrestricted_but_inactive_coupon_is_rejected(): void
    {
        $service = app(CouponEligibilityService::class);
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'per_customer_usage_limit' => null,
        ]);

        $this->assertSame([], $service->validate($coupon, '0.0000'));
        $coupon->update(['is_active' => false]);
        $this->assertContains('coupon_inactive', $service->validate($coupon->fresh(), '0.0000'));
    }

    public function test_unreleased_usage_limits_are_authoritative_and_release_restores_eligibility(): void
    {
        $service = app(CouponEligibilityService::class);
        $customer = User::factory()->customer()->create();
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'usage_limit' => 1,
            'per_customer_usage_limit' => 1,
        ]);
        $usage = $this->usage($coupon, $customer);

        $errors = $service->validate($coupon, '100.0000', $customer);
        $this->assertContains('coupon_usage_limit_reached', $errors);
        $this->assertContains('coupon_customer_limit_reached', $errors);

        CouponUsageRelease::create([
            'coupon_usage_id' => $usage->id,
            'reason' => 'order_cancelled',
            'released_at' => now(),
        ]);
        $this->assertSame([], $service->validate($coupon, '100.0000', $customer));
    }

    public function test_first_order_and_customer_limit_guest_restrictions_are_enforced(): void
    {
        $service = app(CouponEligibilityService::class);
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'is_first_order_only' => true,
            'per_customer_usage_limit' => 1,
        ]);

        $errors = $service->validate($coupon, '100.0000');
        $this->assertContains('coupon_requires_customer', $errors);
        $this->assertContains('coupon_customer_limit_requires_customer', $errors);

        $eligible = User::factory()->customer()->create();
        $this->assertSame([], $service->validate($coupon, '100.0000', $eligible));

        $completed = User::factory()->customer()->create();
        $this->order($completed, 'completed');
        $this->assertContains(
            'coupon_first_order_ineligible',
            $service->validate($coupon, '100.0000', $completed)
        );
    }

    public function test_invalid_coupon_configuration_is_reported_defensively(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => CouponType::Percentage,
            'value' => '101.0000',
        ]);

        $this->assertContains(
            'coupon_value_invalid',
            app(CouponEligibilityService::class)->configurationErrors($coupon)
        );
    }

    private function usage(Coupon $coupon, User $customer): CouponUsage
    {
        $order = $this->order($customer);

        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'coupon_code' => $coupon->code,
            'coupon_type' => $coupon->type,
            'coupon_value' => $coupon->value,
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]);
    }

    private function order(User $customer, string $status = 'pending'): Order
    {
        return Order::create([
            'order_number' => 'ORD-ELIGIBILITY-'.fake()->unique()->numerify('######'),
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_first_name' => 'Coupon',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => $status,
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '100.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '100.0000',
            'placed_at' => now(),
        ]);
    }
}
