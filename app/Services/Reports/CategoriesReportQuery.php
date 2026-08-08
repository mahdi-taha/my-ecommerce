<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class CategoriesReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Categories Report (Current Membership)';
    }

    public function columns(): array
    {
        return ['category' => 'Category', 'currency_code' => 'Currency', 'product_count' => 'Current Products', 'units_sold' => 'Units Sold', 'revenue' => 'Revenue', 'refunded_amount' => 'Refunded', 'net_revenue' => 'Net Revenue', 'distinct_orders' => 'Distinct Orders', 'average_order_value' => 'Average Order Value'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['categories' => $this->query($filters)->count(), 'membership' => 'Current'];
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
            ->selectRaw('order_item_id, SUM(line_amount) refunded_amount')->groupBy('order_item_id');
        $rollup = DB::raw('(WITH RECURSIVE category_rollup AS (SELECT id ancestor_id, id descendant_id FROM categories UNION ALL SELECT category_rollup.ancestor_id, categories.id FROM category_rollup JOIN categories ON categories.parent_id = category_rollup.descendant_id) SELECT ancestor_id, descendant_id FROM category_rollup) as category_rollup');
        $query = $this->commercialOrders($filters)->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('product_categories', 'product_categories.product_id', '=', 'order_items.product_id')->join($rollup, 'category_rollup.descendant_id', '=', 'product_categories.category_id')->join('categories', 'categories.id', '=', 'category_rollup.ancestor_id')
            ->leftJoin('category_translations', fn ($join) => $join->on('category_translations.category_id', '=', 'categories.id')->where('category_translations.locale', 'en'))
            ->leftJoinSub($refunds, 'ri', 'ri.order_item_id', '=', 'order_items.id')->where('order_items.row_total', '>', 0);
        if ($filters->categoryId) {
            $query->where('categories.id', $filters->categoryId);
        }

        return $query->selectRaw('categories.id, category_translations.name category, orders.currency_code, COUNT(DISTINCT order_items.product_id) product_count, SUM(order_items.quantity) units_sold, SUM(order_items.row_total) revenue, COALESCE(SUM(ri.refunded_amount),0) refunded_amount, SUM(order_items.row_total)-COALESCE(SUM(ri.refunded_amount),0) net_revenue, COUNT(DISTINCT orders.id) distinct_orders, SUM(order_items.row_total)/COUNT(DISTINCT orders.id) average_order_value')
            ->groupBy('categories.id', 'category_translations.name', 'orders.currency_code')->orderBy('category');
    }
}
