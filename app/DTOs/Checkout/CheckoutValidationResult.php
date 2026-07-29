<?php

namespace App\DTOs\Checkout;

use App\Models\PaymentMethod;
use App\Models\ShippingMethod;

final readonly class CheckoutValidationResult
{
    public function __construct(
        public array $items,
        public ?ShippingMethod $shippingMethod,
        public ?PaymentMethod $paymentMethod,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
