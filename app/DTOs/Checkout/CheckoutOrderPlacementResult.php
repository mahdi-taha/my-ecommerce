<?php

namespace App\DTOs\Checkout;

use App\Models\Order;

final readonly class CheckoutOrderPlacementResult
{
    public function __construct(
        public bool $successful,
        public ?Order $order,
        public array $errors,
    ) {}

    public static function success(Order $order): self
    {
        return new self(true, $order, []);
    }

    public static function failure(CheckoutValidationError ...$errors): self
    {
        return new self(false, null, $errors);
    }

    public static function failures(array $errors): self
    {
        return new self(false, null, $errors);
    }

    public function failureCodes(): array
    {
        return array_values(array_unique(array_map(
            fn (CheckoutValidationError $error) => $error->code,
            $this->errors
        )));
    }
}
