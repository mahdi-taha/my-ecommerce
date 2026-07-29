<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutSummary;
use App\DTOs\Checkout\CheckoutValidationResult;
use App\Models\Tax;

class CheckoutPricingService
{
    public function calculate(
        CheckoutValidationResult $validation,
        string $currencyCode,
        string $taxMode,
        ?Tax $defaultTax
    ): CheckoutSummary {
        $items = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($validation->items as $validatedItem) {
            $product = $validatedItem->product;
            $quantity = (float) $validatedItem->cartItem->quantity;
            $unitPrice = (float) $product->effectivePrice();
            $displayUnitPrice = $product->displayPrice($taxMode, $defaultTax);
            $rowSubtotal = $unitPrice * $quantity;
            $taxRate = $product->effectiveTaxRate($defaultTax);
            $taxAmount = $rowSubtotal * $taxRate / 100;
            $rowTotal = $rowSubtotal + $taxAmount;
            $effectiveTax = $product->use_default_tax ? $defaultTax : $product->tax;

            $subtotal += $rowSubtotal;
            $taxTotal += $taxAmount;
            $items[] = [
                'cart_item_id' => $validatedItem->cartItem->getKey(),
                'product_id' => $product->getKey(),
                'display_product_id' => $validatedItem->displayProduct->getKey(),
                'sku' => $product->sku,
                'name' => $validatedItem->displayProduct->translations->first()?->name,
                'quantity' => $this->decimal($quantity),
                'unit_price' => $this->decimal($unitPrice),
                'display_unit_price' => $this->decimal($displayUnitPrice),
                'subtotal' => $this->decimal($rowSubtotal),
                'tax_name' => $taxRate > 0 ? $effectiveTax?->name : null,
                'tax_rate' => $this->decimal($taxRate),
                'tax_amount' => $this->decimal($taxAmount),
                'row_total' => $this->decimal($rowTotal),
                'available_quantity' => $validatedItem->availableQuantity,
                'options' => $validatedItem->optionSnapshots,
            ];
        }

        $shippingAmount = (float) $validation->shippingMethod->amount;
        $grandTotal = $subtotal + $taxTotal + $shippingAmount;

        return new CheckoutSummary(
            items: $items,
            subtotal: $this->decimal($subtotal),
            taxTotal: $this->decimal($taxTotal),
            shippingAmount: $this->decimal($shippingAmount),
            grandTotal: $this->decimal($grandTotal),
            currencyCode: $currencyCode,
            taxMode: $taxMode,
            shipping: [
                'id' => $validation->shippingMethod->getKey(),
                'code' => $validation->shippingMethod->code,
                'name' => $validation->shippingMethod->name,
                'type' => $validation->shippingMethod->type->value,
                'amount' => $this->decimal($shippingAmount),
            ],
            tax: ['total' => $this->decimal($taxTotal)],
            errors: [],
        );
    }

    private function decimal(float|int|string $amount): string
    {
        return number_format((float) $amount, 4, '.', '');
    }
}
