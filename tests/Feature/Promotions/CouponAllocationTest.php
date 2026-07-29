<?php

namespace Tests\Feature\Promotions;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Services\CouponAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_discount_is_allocated_deterministically_with_final_remainder(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => CouponType::Fixed,
            'value' => '10.0000',
        ]);

        $result = app(CouponAllocationService::class)->allocate($coupon, [
            ['cart_item_id' => 3, 'subtotal' => '33.3333'],
            ['cart_item_id' => 1, 'subtotal' => '33.3333'],
            ['cart_item_id' => 2, 'subtotal' => '33.3334'],
        ]);

        $this->assertSame('10.0000', $result['discount_total']);
        $this->assertSame('3.3333', $result['allocations'][1]);
        $this->assertSame('3.3333', $result['allocations'][2]);
        $this->assertSame('3.3334', $result['allocations'][3]);
        $this->assertSame(
            10.0,
            array_sum(array_map('floatval', $result['allocations']))
        );
    }

    public function test_percentage_and_fixed_discounts_never_exceed_eligible_subtotal(): void
    {
        $items = [['cart_item_id' => 1, 'subtotal' => '5.0000']];
        $fixed = Coupon::factory()->create(['value' => '20.0000']);
        $percentage = Coupon::factory()->create([
            'type' => CouponType::Percentage,
            'value' => '25.0000',
        ]);

        $this->assertSame(
            '5.0000',
            app(CouponAllocationService::class)->allocate($fixed, $items)['discount_total']
        );
        $this->assertSame(
            '1.2500',
            app(CouponAllocationService::class)->allocate($percentage, $items)['discount_total']
        );
    }
}
