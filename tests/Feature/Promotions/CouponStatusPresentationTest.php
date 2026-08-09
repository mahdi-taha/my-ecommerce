<?php

namespace Tests\Feature\Promotions;

use App\Enums\CouponPresentationStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\CouponUsageRelease;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CouponStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_precedence_and_time_boundaries_match_coupon_eligibility(): void
    {
        $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');

        $inactive = $this->coupon([
            'is_active' => false,
            'starts_at' => $now->addHour(),
            'ends_at' => $now->subHour(),
            'usage_limit' => 1,
        ]);
        $this->assertSame(CouponPresentationStatus::Inactive, $inactive->presentationStatus(1, $now));

        $scheduled = $this->coupon([
            'starts_at' => $now->addSecond(),
            'ends_at' => $now->addHour(),
            'usage_limit' => 1,
        ]);
        $this->assertSame(CouponPresentationStatus::Scheduled, $scheduled->presentationStatus(1, $now));

        $expired = $this->coupon([
            'starts_at' => $now->subHour(),
            'ends_at' => $now,
            'usage_limit' => 1,
        ]);
        $this->assertSame(CouponPresentationStatus::Expired, $expired->presentationStatus(1, $now));

        $exhausted = $this->coupon([
            'starts_at' => $now,
            'ends_at' => $now->addHour(),
            'usage_limit' => 1,
        ]);
        $this->assertSame(CouponPresentationStatus::UsageExhausted, $exhausted->presentationStatus(1, $now));
        $this->assertSame(CouponPresentationStatus::Active, $exhausted->presentationStatus(0, $now));
    }

    public function test_unlimited_usage_and_time_advancement_update_presentation_without_queries(): void
    {
        $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');
        $coupon = $this->coupon([
            'starts_at' => $now,
            'ends_at' => $now->addHour(),
            'usage_limit' => null,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame(CouponPresentationStatus::Active, $coupon->presentationStatus(10_000, $now));
        $this->assertSame(CouponPresentationStatus::Expired, $coupon->presentationStatus(10_000, $now->addHour()));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_admin_index_renders_all_derived_statuses_and_excludes_released_usage(): void
    {
        $now = CarbonImmutable::parse('2026-08-02 12:00:00', 'UTC');
        $this->travelTo($now);

        $this->coupon(['is_active' => false]);
        $this->coupon(['starts_at' => $now->addHour()]);
        $this->coupon(['ends_at' => $now]);
        $this->coupon();

        $exhausted = $this->coupon(['usage_limit' => 1]);
        $this->usage($exhausted);

        $released = $this->coupon(['usage_limit' => 1]);
        $releasedUsage = $this->usage($released);
        CouponUsageRelease::create([
            'coupon_usage_id' => $releasedUsage->id,
            'reason' => 'order_cancelled',
            'released_at' => $now,
        ]);

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.coupons.index'));

        $response
            ->assertOk()
            ->assertSee('<span class="badge bg-danger">', false)
            ->assertSeeText('Inactive')
            ->assertSee('<span class="badge bg-info">', false)
            ->assertSeeText('Scheduled')
            ->assertSee('<span class="badge bg-secondary">', false)
            ->assertSeeText('Expired')
            ->assertSee('<span class="badge bg-warning text-dark">', false)
            ->assertSeeText('Usage Exhausted')
            ->assertSee('<span class="badge bg-success">', false)
            ->assertSeeText('Active');

        $this->assertSame(0, $released->unreleasedUsages()->count());
    }

    public function test_presentation_enum_owns_labels_and_badge_classes(): void
    {
        $this->assertSame([
            ['Active', 'bg-success'],
            ['Scheduled', 'bg-info'],
            ['Expired', 'bg-secondary'],
            ['Usage Exhausted', 'bg-warning text-dark'],
            ['Inactive', 'bg-danger'],
        ], array_map(
            fn (CouponPresentationStatus $status): array => [$status->label(), $status->badgeClass()],
            CouponPresentationStatus::cases()
        ));
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::factory()->create(array_merge(['is_active' => true], $attributes));
    }

    private function usage(Coupon $coupon): CouponUsage
    {
        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $this->order()->id,
            'coupon_code' => $coupon->code,
            'coupon_type' => CouponType::Fixed,
            'coupon_value' => '10.0000',
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ORD-COUPON-'.str()->random(8),
            'customer_email' => 'coupon@example.test',
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
