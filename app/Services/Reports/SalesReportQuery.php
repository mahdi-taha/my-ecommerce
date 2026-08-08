<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use App\DTOs\Reports\ReportFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Traversable;

class SalesReportQuery extends AbstractReportQuery implements ReportQuery
{
    public function title(): string
    {
        return 'Sales Report';
    }

    public function columns(): array
    {
        return ['currency_code' => 'Currency', 'gross_sales' => 'Gross Sales', 'discounts' => 'Discounts', 'tax' => 'Tax', 'shipping' => 'Shipping', 'refunded_merchandise' => 'Refunded Merchandise', 'shipping_deductions' => 'Shipping Deductions', 'company_shipping_losses' => 'Company Shipping Losses', 'customer_refunds' => 'Customer Refunds', 'net_sales' => 'Net Sales', 'average_order_value' => 'Average Order Value', 'order_count' => 'Orders'];
    }

    public function summary(ReportFilters $filters): array
    {
        return ['currencies' => $this->query($filters)->count(), 'order_count' => $this->commercialOrders($filters)->count()];
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
        $orders = $this->commercialOrders($filters)
            ->selectRaw('currency_code, SUM(subtotal) gross_sales, SUM(discount_total) discounts, SUM(tax_total) tax, SUM(shipping_total) shipping, SUM(grand_total) order_value, COUNT(*) order_count')
            ->groupBy('currency_code');
        $refunds = $this->applyRefundFilters(DB::table('refunds'), $filters)
            ->selectRaw('currency_code, SUM(merchandise_subtotal - discount_amount) refunded_merchandise, SUM(shipping_deduction) shipping_deductions, SUM(company_shipping_loss) company_shipping_losses, SUM(customer_refund_amount) customer_refunds')
            ->groupBy('currency_code');
        $currencies = DB::query()->fromSub($orders->clone()->select('currency_code')->union($refunds->clone()->select('currency_code')), 'report_currencies')->select('currency_code')->distinct();

        return DB::query()->fromSub($currencies, 'currencies')
            ->leftJoinSub($orders, 'sales', 'sales.currency_code', '=', 'currencies.currency_code')
            ->leftJoinSub($refunds, 'refund_totals', 'refund_totals.currency_code', '=', 'currencies.currency_code')
            ->selectRaw('currencies.currency_code, COALESCE(gross_sales,0) gross_sales, COALESCE(discounts,0) discounts, COALESCE(tax,0) tax, COALESCE(shipping,0) shipping, COALESCE(refunded_merchandise,0) refunded_merchandise, COALESCE(shipping_deductions,0) shipping_deductions, COALESCE(company_shipping_losses,0) company_shipping_losses, COALESCE(customer_refunds,0) customer_refunds, COALESCE(gross_sales,0)-COALESCE(discounts,0)-COALESCE(refunded_merchandise,0) net_sales, CASE WHEN COALESCE(order_count,0)=0 THEN 0 ELSE order_value/order_count END average_order_value, COALESCE(order_count,0) order_count')
            ->orderBy('currencies.currency_code');
    }
}
