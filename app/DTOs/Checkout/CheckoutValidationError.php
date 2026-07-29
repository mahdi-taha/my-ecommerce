<?php

namespace App\DTOs\Checkout;

final readonly class CheckoutValidationError
{
    public function __construct(
        public string $code,
        public string $field,
        public string $message,
        public ?int $cartItemId = null,
        public ?int $productId = null,
    ) {}

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'field' => $this->field,
            'message' => $this->message,
            'cart_item_id' => $this->cartItemId,
            'product_id' => $this->productId,
        ];
    }
}
