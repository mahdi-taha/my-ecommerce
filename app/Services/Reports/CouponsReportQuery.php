<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use App\Enums\PaymentStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class CouponsReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Coupons Report';
    }

    public function columns(): array
    {
        return ['coupon_code' => 'Coupon', 'currency_code' => 'Currency', 'total_usage' => 'Total Usage', 'effective_usage' => 'Effective Usage', 'released_usage' => 'Released Usage', 'total_discount' => 'Discount', 'revenue_generated' => 'Revenue', 'average_order_value' => 'Average Order Value'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['coupons' => $this->query($filters)->count()];
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
        $eligible = "'".implode("','", [PaymentStatus::Paid->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value])."'";

        return $this->applyOrderFilters(DB::table('coupon_usages')->join('orders', 'orders.id', '=', 'coupon_usages.order_id')->leftJoin('coupon_usage_releases', 'coupon_usage_releases.coupon_usage_id', '=', 'coupon_usages.id'), $filters)
            ->selectRaw("coupon_usages.coupon_id, coupon_usages.coupon_code, orders.currency_code, COUNT(*) total_usage, SUM(CASE WHEN coupon_usage_releases.id IS NULL THEN 1 ELSE 0 END) effective_usage, SUM(CASE WHEN coupon_usage_releases.id IS NOT NULL THEN 1 ELSE 0 END) released_usage, SUM(CASE WHEN coupon_usage_releases.id IS NULL AND orders.payment_status IN ($eligible) THEN coupon_usages.discount_amount ELSE 0 END) total_discount, SUM(CASE WHEN coupon_usage_releases.id IS NULL AND orders.payment_status IN ($eligible) THEN orders.grand_total ELSE 0 END) revenue_generated, CASE WHEN SUM(CASE WHEN coupon_usage_releases.id IS NULL AND orders.payment_status IN ($eligible) THEN 1 ELSE 0 END)=0 THEN 0 ELSE SUM(CASE WHEN coupon_usage_releases.id IS NULL AND orders.payment_status IN ($eligible) THEN orders.grand_total ELSE 0 END)/SUM(CASE WHEN coupon_usage_releases.id IS NULL AND orders.payment_status IN ($eligible) THEN 1 ELSE 0 END) END average_order_value")
            ->groupBy('coupon_usages.coupon_id', 'coupon_usages.coupon_code', 'orders.currency_code')->orderBy('coupon_usages.coupon_code');
    }
}
