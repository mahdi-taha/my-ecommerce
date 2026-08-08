<?php

namespace Tests\Feature\Refunds;

use App\Enums\ShippingTreatment;
use App\Services\RefundService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class RefundIsolationTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_refund_does_not_mutate_inventory_coupon_or_payment_attempt_ledgers(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);
        $before = [
            'inventory' => DB::table('inventory_movements')->count(),
            'coupons' => DB::table('coupon_usages')->count(),
            'attempts' => DB::table('payment_attempts')->count(),
        ];

        app(RefundService::class)->create($order, $admin, $this->data($item->id), str_repeat('2', 64));

        $this->assertSame($before['inventory'], DB::table('inventory_movements')->count());
        $this->assertSame($before['coupons'], DB::table('coupon_usages')->count());
        $this->assertSame($before['attempts'], DB::table('payment_attempts')->count());
    }

    public function test_refund_creation_has_a_bounded_query_ceiling(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries++;
        });

        app(RefundService::class)->create($order, $admin, $this->data($item->id), str_repeat('3', 64));

        $this->assertLessThanOrEqual(30, $queries);
    }

    private function data(int $itemId): array
    {
        return [
            'items' => [['order_item_id' => $itemId, 'quantity' => '1']],
            'return_shipping_cost' => '0',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
        ];
    }
}
