<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Enums\ShippingTreatment;
use App\Services\RefundService;
use App\Services\Reports\RefundsReportQuery;
use App\Services\Reports\TaxesReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class RefundTaxReportTest extends TestCase
{
    use CreatesRefundOrders, RefreshDatabase;

    public function test_refund_report_preserves_shipping_components(): void
    {
        [$order,,$admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);
        app(RefundService::class)->create($order, $admin, ['items' => [['order_item_id' => $item->id, 'quantity' => '1']], 'return_shipping_cost' => '5', 'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value], str_repeat('7', 64));
        $row = app(RefundsReportQuery::class)->rows($this->filters())->items()[0];
        $this->assertEquals(5, $row->company_shipping_loss);
        $this->assertEquals(0, $row->shipping_deduction);
    }

    public function test_tax_report_uses_line_snapshot_rate(): void
    {
        [$order] = $this->paidRefundOrder();
        $this->refundOrderItem($order, ['tax_name' => 'VAT', 'tax_rate' => '10', 'tax_amount' => '10', 'row_total' => '110']);
        $row = app(TaxesReportQuery::class)->rows($this->filters())->items()[0];
        $this->assertEquals(10, $row->tax_rate);
        $this->assertEquals(10, $row->tax_collected);
    }

    private function filters(): ReportFilters
    {
        return new ReportFilters(null, null, null, null, null, null, null, null, null, null, null, 25);
    }
}
