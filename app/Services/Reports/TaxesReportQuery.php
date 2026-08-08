<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class TaxesReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Taxes Report';
    }

    public function columns(): array
    {
        return ['tax_name' => 'Tax', 'tax_rate' => 'Rate', 'currency_code' => 'Currency', 'taxable_sales' => 'Taxable Sales', 'tax_collected' => 'Tax Collected', 'refunded_taxable_sales' => 'Refunded Taxable Sales', 'refunded_tax' => 'Refunded Tax', 'net_tax' => 'Net Tax'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['tax_rates' => $this->query($filters)->count()];
    }

    public function rows(ReportFilters $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters->perPage);
    }

    public function exportRows(ReportFilters $filters): Traversable
    {
        return $this->paginatedExport(fn (int $page) => $this->query($filters)->paginate(500, page: $page));
    }

    private function query(ReportFilters $filters): Builder
    {
        $refunds = $this->applyRefundFilters(DB::table('refund_items')->join('refunds', 'refunds.id', '=', 'refund_items.refund_id'), $filters)->selectRaw('refund_items.order_item_id, SUM(refund_items.subtotal_amount-refund_items.discount_amount) refunded_taxable, SUM(refund_items.tax_amount) refunded_tax')->groupBy('refund_items.order_item_id');

        return $this->commercialOrders($filters)->join('order_items', 'order_items.order_id', '=', 'orders.id')->leftJoinSub($refunds, 'ri', 'ri.order_item_id', '=', 'order_items.id')->where('order_items.tax_rate', '>', 0)
            ->selectRaw('order_items.tax_name, order_items.tax_rate, orders.currency_code, SUM(order_items.row_subtotal-order_items.discount_amount) taxable_sales, SUM(order_items.tax_amount) tax_collected, COALESCE(SUM(ri.refunded_taxable),0) refunded_taxable_sales, COALESCE(SUM(ri.refunded_tax),0) refunded_tax, SUM(order_items.tax_amount)-COALESCE(SUM(ri.refunded_tax),0) net_tax')
            ->groupBy('order_items.tax_name', 'order_items.tax_rate', 'orders.currency_code')->orderBy('order_items.tax_rate');
    }
}
