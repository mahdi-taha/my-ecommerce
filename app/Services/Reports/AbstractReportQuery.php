<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Enums\PaymentStatus;
use Illuminate\Database\Query\Builder;
use Traversable;

abstract class AbstractReportQuery
{
    protected function commercialOrders(ReportFilters $filters): Builder
    {
        return $this->applyOrderFilters(
            \DB::table('orders')->whereIn('orders.payment_status', [
                PaymentStatus::Paid->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ]),
            $filters,
        );
    }

    protected function applyOrderFilters(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->when($filters->start, fn (Builder $query, $date) => $query->where('orders.placed_at', '>=', $date))
            ->when($filters->endExclusive, fn (Builder $query, $date) => $query->where('orders.placed_at', '<', $date))
            ->when($filters->currency, fn (Builder $query, $value) => $query->where('orders.currency_code', $value))
            ->when($filters->customerId, fn (Builder $query, $value) => $query->where('orders.user_id', $value))
            ->when($filters->orderStatus, fn (Builder $query, $value) => $query->where('orders.status', $value))
            ->when($filters->paymentStatus, fn (Builder $query, $value) => $query->where('orders.payment_status', $value))
            ->when($filters->fulfillmentStatus, fn (Builder $query, $value) => $query->where('orders.fulfillment_status', $value));
    }

    protected function applyRefundFilters(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->when($filters->start, fn (Builder $query, $date) => $query->where('refunds.refunded_at', '>=', $date))
            ->when($filters->endExclusive, fn (Builder $query, $date) => $query->where('refunds.refunded_at', '<', $date))
            ->when($filters->currency, fn (Builder $query, $value) => $query->where('refunds.currency_code', $value))
            ->when($filters->administratorId, fn (Builder $query, $value) => $query->where('refunds.created_by', $value))
            ->when($filters->shippingTreatment, fn (Builder $query, $value) => $query->where('refunds.shipping_treatment', $value));
    }

    /** @param callable(int): \Illuminate\Contracts\Pagination\LengthAwarePaginator $page */
    protected function paginatedExport(callable $page): Traversable
    {
        $number = 1;
        do {
            $rows = $page($number++);
            foreach ($rows->items() as $row) {
                yield (array) $row;
            }
        } while ($rows->hasMorePages());
    }
}
