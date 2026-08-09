<?php

namespace App\Services\Reports;

use App\Contracts\Reports\ReportQuery;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;

class ReportRegistry
{
    private const REPORTS = [
        'sales' => SalesReportQuery::class,
        'orders' => OrdersReportQuery::class,
        'products' => ProductsReportQuery::class,
        'categories' => CategoriesReportQuery::class,
        'customers' => CustomersReportQuery::class,
        'payments' => PaymentsReportQuery::class,
        'refunds' => RefundsReportQuery::class,
        'inventory' => InventoryReportQuery::class,
        'coupons' => CouponsReportQuery::class,
        'taxes' => TaxesReportQuery::class,
        'reviews' => ReviewsReportQuery::class,
    ];

    private const FILTERS = [
        'sales' => ['date', 'customer_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'orders' => ['date', 'customer_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'products' => ['date', 'customer_id', 'product_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'categories' => ['date', 'customer_id', 'category_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'customers' => ['date', 'customer_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'payments' => ['date', 'customer_id', 'payment_method', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'refunds' => ['date', 'administrator_id', 'shipping_treatment', 'per_page'],
        'inventory' => ['product_id', 'per_page'],
        'coupons' => ['date', 'customer_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'taxes' => ['date', 'customer_id', 'order_status', 'payment_status', 'fulfillment_status', 'per_page'],
        'reviews' => ['date', 'product_id', 'per_page'],
    ];

    public function __construct(private Container $container) {}

    /** @return list<string> */
    public function names(): array
    {
        return array_keys(self::REPORTS);
    }

    public function get(string $name): ReportQuery
    {
        if (! isset(self::REPORTS[$name])) {
            throw ValidationException::withMessages(['report' => 'The selected report is invalid.']);
        }

        return $this->container->make(self::REPORTS[$name]);
    }

    public function filters(string $name): array
    {
        if (! isset(self::FILTERS[$name])) {
            throw ValidationException::withMessages(['report' => 'The selected report is invalid.']);
        }

        return self::FILTERS[$name];
    }
}
