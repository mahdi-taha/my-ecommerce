<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Traversable;

class OrdersReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Orders Report';
    }

    public function columns(): array
    {
        return ['order_number' => 'Order', 'customer' => 'Customer', 'currency_code' => 'Currency', 'subtotal' => 'Subtotal', 'discount_total' => 'Discount', 'tax_total' => 'Tax', 'shipping_total' => 'Shipping', 'grand_total' => 'Total', 'status' => 'Order Status', 'payment_status' => 'Payment Status', 'fulfillment_status' => 'Fulfillment', 'placed_at' => 'Created'];
    }

    public function summary(ReportFilters $filters): array
    {
        $row = $this->base($filters)->selectRaw('COUNT(*) order_count, COALESCE(SUM(grand_total),0) total')->first();

        return ['order_count' => (int) $row->order_count, 'total' => $row->total];
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
        return $this->base($filters)
            ->select(['orders.*', 'customer_email as customer'])
            ->orderByDesc('placed_at')->orderByDesc('id');
    }

    private function base(ReportFilters $filters): Builder
    {
        return $this->applyOrderFilters(\DB::table('orders'), $filters);
    }
}
