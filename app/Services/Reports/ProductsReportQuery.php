<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class ProductsReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Products Report';
    }

    public function columns(): array
    {
        return ['sku' => 'SKU', 'name' => 'Product', 'currency_code' => 'Currency', 'units_sold' => 'Units Sold', 'units_refunded' => 'Units Refunded', 'average_selling_price' => 'Average Selling Price', 'revenue' => 'Revenue', 'refunded_amount' => 'Refunded', 'net_revenue' => 'Net Revenue'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['products' => $this->query($filters)->count()];
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
        $refunds = $this->applyRefundFilters(DB::table('refund_items')->join('refunds', 'refunds.id', '=', 'refund_items.refund_id'), $filters)
            ->selectRaw('order_item_id, SUM(quantity) refunded_quantity, SUM(line_amount) refunded_amount')->groupBy('order_item_id');

        return $this->commercialOrders($filters)->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoinSub($refunds, 'ri', 'ri.order_item_id', '=', 'order_items.id')
            ->where('order_items.quantity', '>', 0)->where('order_items.row_total', '>', 0)
            ->when($filters->productId, fn (Builder $q, $id) => $q->where('order_items.product_id', $id))
            ->selectRaw('order_items.product_id, order_items.sku, order_items.name, orders.currency_code, SUM(order_items.quantity) units_sold, COALESCE(SUM(ri.refunded_quantity),0) units_refunded, SUM(order_items.row_subtotal-order_items.discount_amount)/SUM(order_items.quantity) average_selling_price, SUM(order_items.row_total) revenue, COALESCE(SUM(ri.refunded_amount),0) refunded_amount, SUM(order_items.row_total)-COALESCE(SUM(ri.refunded_amount),0) net_revenue')
            ->groupBy('order_items.product_id', 'order_items.sku', 'order_items.name', 'orders.currency_code')
            ->orderByDesc('units_sold')->orderBy('order_items.sku');
    }
}
