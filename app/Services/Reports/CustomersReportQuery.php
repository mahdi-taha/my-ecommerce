<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class CustomersReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Customers Report';
    }

    public function columns(): array
    {
        return ['customer' => 'Customer', 'registered_at' => 'Registered', 'currency_code' => 'Currency', 'order_count' => 'Orders', 'total_spent' => 'Total Spent', 'refunded_amount' => 'Refunded', 'average_order_value' => 'Average Order Value', 'last_order' => 'Last Order'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['registrations' => $this->registered($filters)->count(), 'customers_with_orders' => $this->query($filters)->where('order_count', '>', 0)->count(), 'customers_without_orders' => $this->query($filters)->where('order_count', '=', 0)->count()];
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
        $orders = $this->commercialOrders($filters)->join('order_payments', 'order_payments.order_id', '=', 'orders.id')
            ->selectRaw('orders.user_id, orders.currency_code, COUNT(DISTINCT orders.id) order_count, SUM(order_payments.paid_amount) collected, SUM(orders.grand_total) order_value, MAX(orders.placed_at) last_order')->whereNotNull('orders.user_id')->groupBy('orders.user_id', 'orders.currency_code');
        $refunds = $this->applyRefundFilters(DB::table('refunds')->join('orders', 'orders.id', '=', 'refunds.order_id'), $filters)
            ->selectRaw('orders.user_id, refunds.currency_code, SUM(refunds.customer_refund_amount) refunded_amount')->whereNotNull('orders.user_id')->groupBy('orders.user_id', 'refunds.currency_code');

        return DB::table('users')->leftJoinSub($orders, 'sales', 'sales.user_id', '=', 'users.id')->leftJoinSub($refunds, 'rr', fn ($join) => $join->on('rr.user_id', '=', 'users.id')->on('rr.currency_code', '=', 'sales.currency_code'))
            ->where('users.account_type', 'customer')->when($filters->customerId, fn (Builder $q, $id) => $q->where('users.id', $id))
            ->selectRaw('users.id, users.name customer, users.created_at registered_at, sales.currency_code, COALESCE(order_count,0) order_count, COALESCE(collected,0)-COALESCE(refunded_amount,0) total_spent, COALESCE(refunded_amount,0) refunded_amount, CASE WHEN COALESCE(order_count,0)=0 THEN 0 ELSE order_value/order_count END average_order_value, last_order')
            ->orderByDesc('total_spent')->orderBy('users.id');
    }

    private function registered(ReportFilters $filters): Builder
    {
        return DB::table('users')->where('account_type', 'customer')->where('has_account', true)
            ->when($filters->start, fn (Builder $q, $v) => $q->where('created_at', '>=', $v))->when($filters->endExclusive,fn (Builder $q,$v) => $q->where('created_at','<',$v));
    }
}
