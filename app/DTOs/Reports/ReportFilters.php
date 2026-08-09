<?php

namespace App\DTOs\Reports;

use Carbon\CarbonImmutable;

final readonly class ReportFilters
{
    public function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $endExclusive,
        public ?int $customerId,
        public ?int $categoryId,
        public ?int $productId,
        public ?int $administratorId,
        public ?string $paymentMethod,
        public ?string $orderStatus,
        public ?string $paymentStatus,
        public ?string $fulfillmentStatus,
        public ?string $shippingTreatment,
        public int $perPage,
    ) {}

    /** @return array<string, scalar|null> */
    public function query(): array
    {
        return array_filter([
            'date_from' => $this->start?->setTimezone($this->storeTimezone())->toDateString(),
            'date_to' => $this->endExclusive?->subDay()->setTimezone($this->storeTimezone())->toDateString(),
            'customer_id' => $this->customerId,
            'category_id' => $this->categoryId,
            'product_id' => $this->productId,
            'administrator_id' => $this->administratorId,
            'payment_method' => $this->paymentMethod,
            'order_status' => $this->orderStatus,
            'payment_status' => $this->paymentStatus,
            'fulfillment_status' => $this->fulfillmentStatus,
            'shipping_treatment' => $this->shippingTreatment,
            'per_page' => $this->perPage,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function storeTimezone(): string
    {
        return (string) setting('localization.timezone', config('app.timezone'));
    }
}
