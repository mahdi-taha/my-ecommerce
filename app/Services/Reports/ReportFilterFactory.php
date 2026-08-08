<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ReportFilters;
use Carbon\CarbonImmutable;

class ReportFilterFactory
{
    /** @param array<string, mixed> $data */
    public function make(array $data): ReportFilters
    {
        $storeTimezone = (string) setting('localization.timezone', config('app.timezone'));
        $databaseTimezone = (string) config('app.timezone');

        return new ReportFilters(
            start: isset($data['date_from']) ? CarbonImmutable::parse($data['date_from'], $storeTimezone)->startOfDay()->setTimezone($databaseTimezone) : null,
            endExclusive: isset($data['date_to']) ? CarbonImmutable::parse($data['date_to'], $storeTimezone)->addDay()->startOfDay()->setTimezone($databaseTimezone) : null,
            currency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            customerId: isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            administratorId: isset($data['administrator_id']) ? (int) $data['administrator_id'] : null,
            paymentMethod: $data['payment_method'] ?? null,
            orderStatus: $data['order_status'] ?? null,
            paymentStatus: $data['payment_status'] ?? null,
            fulfillmentStatus: $data['fulfillment_status'] ?? null,
            shippingTreatment: $data['shipping_treatment'] ?? null,
            perPage: (int) ($data['per_page'] ?? 25),
        );
    }
}
