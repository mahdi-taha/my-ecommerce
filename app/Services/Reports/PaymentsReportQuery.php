<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class PaymentsReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Payments Report';
    }

    public function columns(): array
    {
        return ['method_name' => 'Payment Method', 'status' => 'Status', 'currency_code' => 'Currency', 'payment_count' => 'Payments', 'obligation_amount' => 'Obligation', 'collected' => 'Collected', 'refunded' => 'Refunded'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['payments' => $this->base($filters)->count()];
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
        $refunds = $this->applyRefundFilters(DB::table('refunds'), $filters)->selectRaw('order_payment_id, SUM(customer_refund_amount) refunded')->groupBy('order_payment_id');

        return $this->base($filters)->leftJoinSub($refunds, 'rr', 'rr.order_payment_id', '=', 'order_payments.id')
            ->selectRaw('order_payments.method_code, order_payments.method_name, order_payments.status, order_payments.currency_code, COUNT(*) payment_count, SUM(order_payments.amount) obligation_amount, SUM(order_payments.paid_amount) collected, COALESCE(SUM(rr.refunded),0) refunded')
            ->groupBy('order_payments.method_code', 'order_payments.method_name', 'order_payments.status', 'order_payments.currency_code')->orderBy('order_payments.method_name')->orderBy('order_payments.status');
    }

    private function base(ReportFilters $filters): Builder
    {
        return $this->applyOrderFilters(DB::table('order_payments')->join('orders', 'orders.id', '=', 'order_payments.order_id'), $filters)
            ->when($filters->paymentMethod, fn (Builder $q, $v) => $q->where('order_payments.method_code', $v));
    }
}
