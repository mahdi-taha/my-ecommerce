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
}
