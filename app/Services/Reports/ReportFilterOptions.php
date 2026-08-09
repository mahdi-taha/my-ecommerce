<?php

namespace App\Services\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingTreatment;
use App\Models\PaymentMethod;

class ReportFilterOptions
{
    public function __construct(private ReportLookupService $lookups) {}

    public function for(ReportFilters $filters, array $enabled): array
    {
        return [
            'selected' => $this->lookups->selectedOptions($filters, $enabled),
            'order_statuses' => $this->enumOptions(OrderStatus::cases()),
            'payment_statuses' => $this->enumOptions(PaymentStatus::cases()),
            'fulfillment_statuses' => $this->enumOptions(FulfillmentStatus::cases()),
            'shipping_treatments' => $this->enumOptions(ShippingTreatment::cases()),
            'payment_methods' => in_array('payment_method', $enabled, true)
                ? PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->pluck('name', 'code')->all()
                : [],
            'lookup_urls' => [
                'customer_id' => route('admin.reports.lookups.customers'),
                'product_id' => route('admin.reports.lookups.products'),
                'category_id' => route('admin.reports.lookups.categories'),
                'administrator_id' => route('admin.reports.lookups.administrators'),
            ],
        ];
    }

    private function enumOptions(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($case) => [
            $case->value => str($case->value)->replace('_', ' ')->title()->toString(),
        ])->all();
    }
}
