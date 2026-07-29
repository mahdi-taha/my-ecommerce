<?php

namespace App\DTOs\Checkout;

use App\Models\CartItem;
use App\Models\Product;

final readonly class ValidatedCheckoutItem
{
    public function __construct(
        public CartItem $cartItem,
        public Product $product,
        public Product $displayProduct,
        public array $optionSnapshots,
        public string $availableQuantity,
    ) {}
}
