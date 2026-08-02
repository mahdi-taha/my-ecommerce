<?php

namespace App\DTOs\Checkout;

use App\Enums\CartItemType;
use App\Models\Product;

final readonly class ValidatedCheckoutItem
{
    public function __construct(
        public int $lineId,
        public string $quantity,
        public CartItemType $selectionType,
        public Product $product,
        public Product $displayProduct,
        public array $optionSnapshots,
        public string $availableQuantity,
    ) {}
}
