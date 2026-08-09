<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Enums\ShippingTreatment;
use App\Services\RefundService;
use App\Services\Reports\OrdersReportQuery;
use App\Services\Reports\SalesReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class SalesOrdersReportTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_sales_separates_currencies_and_uses_refund_event_date(): void
    {
        CarbonImmutable::setTestNow('2026-08-10 12:00:00');
        [$oldOrder, , $admin] = $this->paidRefundOrder(['placed_at' => '2026-07-15 10:00:00']);
        $oldItem = $this->refundOrderItem($oldOrder);
        app(RefundService::class)->create($oldOrder, $admin, [
            'items' => [['order_item_id' => $oldItem->id, 'quantity' => '1']],
            'return_shipping_cost' => '10',
            'shipping_treatment' => ShippingTreatment::DeductFromRefund->value,
        ], str_repeat('6', 64));
        $this->paidRefundOrder(['placed_at' => '2026-08-05 10:00:00']);
        $this->paidRefundOrder(['currency_code' => 'EUR', 'placed_at' => '2026-08-06 10:00:00']);

        $rows = app(SalesReportQuery::class)->rows($this->filters('2026-08-01', '2026-09-01'));

        $this->assertCount(2, $rows);
        $usd = collect($rows->items())->firstWhere('currency_code', 'USD');
        $this->assertEquals(100, $usd->gross_sales);
        $this->assertEquals(100, $usd->refunded_merchandise);
        $this->assertEquals(10, $usd->shipping_deductions);
        $this->assertEquals(0, $usd->net_sales);
    }

    public function test_orders_report_combines_status_and_date_filters(): void
    {
        $this->paidRefundOrder(['placed_at' => '2026-08-05 10:00:00', 'status' => 'completed']);
        $this->paidRefundOrder(['placed_at' => '2026-07-05 10:00:00', 'status' => 'completed']);
        $filters = $this->filters('2026-08-01', '2026-09-01', 'completed');

        $rows = app(OrdersReportQuery::class)->rows($filters);
        $this->assertSame(1, $rows->total());
        $this->assertSame(1, app(OrdersReportQuery::class)->summary($filters)['order_count']);
    }

    private function filters(string $start, string $end, ?string $orderStatus = null): ReportFilters
    {
        return new ReportFilters(CarbonImmutable::parse($start), CarbonImmutable::parse($end), null, null, null, null, null, $orderStatus, null, null, null, 25);
    }
}
