<?php

namespace App\DTOs\Checkout;

final readonly class CheckoutSummary
{
    public function __construct(
        public array $items,
        public string $subtotal,
        public string $taxTotal,
        public string $shippingAmount,
        public string $grandTotal,
        public string $currencyCode,
        public string $taxMode,
        public ?array $shipping,
        public array $tax,
        public array $errors,
    ) {}

    public static function invalid(
        array $errors,
        string $currencyCode,
        string $taxMode
    ): self {
        return new self(
            items: [],
            subtotal: '0.0000',
            taxTotal: '0.0000',
            shippingAmount: '0.0000',
            grandTotal: '0.0000',
            currencyCode: $currencyCode,
            taxMode: $taxMode,
            shipping: null,
            tax: ['total' => '0.0000'],
            errors: array_map(
                fn (CheckoutValidationError $error) => $error->toArray(),
                $errors
            ),
        );
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
