<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class RefundsReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Refunds Report';
    }

    public function columns(): array
    {
        return ['refund_number' => 'Refund', 'order_number' => 'Order', 'administrator' => 'Administrator', 'currency_code' => 'Currency', 'merchandise_amount' => 'Merchandise', 'customer_refund_amount' => 'Customer Refund', 'shipping_deduction' => 'Shipping Deduction', 'company_shipping_loss' => 'Company Shipping Loss', 'shipping_treatment' => 'Treatment', 'refunded_at' => 'Date'];
    }

    public function summary(ReportFilters $filters): array
    {
        $rows = $this->base($filters)->selectRaw('refunds.currency_code, COUNT(*) refund_count, SUM(refunds.merchandise_amount) merchandise_refunded, SUM(refunds.customer_refund_amount) customer_refunded, SUM(refunds.shipping_deduction) shipping_deductions, SUM(refunds.company_shipping_loss) company_shipping_losses')->groupBy('refunds.currency_code')->get();

        return ['currencies' => $rows->count(), 'refund_count' => (int) $rows->sum('refund_count')];
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
        return $this->base($filters)->leftJoin('users', 'users.id', '=', 'refunds.created_by')->join('orders', 'orders.id', '=', 'refunds.order_id')->select(['refunds.*', 'orders.order_number', 'users.name as administrator'])->orderByDesc('refunds.refunded_at')->orderByDesc('refunds.id');
    }

    private function base(ReportFilters $filters): Builder
    {
        return $this->applyRefundFilters(DB::table('refunds'), $filters);
    }
}
