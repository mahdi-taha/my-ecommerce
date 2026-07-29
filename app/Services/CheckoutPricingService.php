<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutSummary;
use App\DTOs\Checkout\CheckoutValidationResult;
use App\Models\Coupon;
use App\Models\Tax;

class CheckoutPricingService
{
    public function __construct(private CouponAllocationService $allocationService) {}

    public function calculate(
        CheckoutValidationResult $validation,
        string $currencyCode,
        string $taxMode,
        ?Tax $defaultTax,
        ?Coupon $coupon = null,
        array $warnings = [],
    ): CheckoutSummary {
        $allocation = $coupon
            ? $this->allocationService->allocate(
                $coupon,
                collect($validation->items)->map(fn ($validatedItem) => [
                    'cart_item_id' => (int) $validatedItem->cartItem->getKey(),
                    'subtotal' => $this->decimal(
                        (float) $validatedItem->product->effectivePrice()
                        * (float) $validatedItem->cartItem->quantity
                    ),
                ])->all()
            )
            : ['discount_total' => '0.0000', 'allocations' => []];
        $items = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($validation->items as $validatedItem) {
            $product = $validatedItem->product;
            $quantity = (float) $validatedItem->cartItem->quantity;
            $unitPrice = (float) $product->effectivePrice();
            $displayUnitPrice = $product->displayPrice($taxMode, $defaultTax);
            $rowSubtotal = $unitPrice * $quantity;
            $discountAmount = (float) ($allocation['allocations'][$validatedItem->cartItem->getKey()] ?? 0);
            $discountedSubtotal = max(0, $rowSubtotal - $discountAmount);
            $taxRate = $product->effectiveTaxRate($defaultTax);
            $taxAmount = $discountedSubtotal * $taxRate / 100;
            $rowTotal = $discountedSubtotal + $taxAmount;
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
                'discount_amount' => $this->decimal($discountAmount),
                'tax_name' => $taxRate > 0 ? $effectiveTax?->name : null,
                'tax_rate' => $this->decimal($taxRate),
                'tax_amount' => $this->decimal($taxAmount),
                'row_total' => $this->decimal($rowTotal),
                'available_quantity' => $validatedItem->availableQuantity,
                'options' => $validatedItem->optionSnapshots,
            ];
        }

        $shippingAmount = (float) $validation->shippingMethod->amount;
        $grandTotal = $subtotal - (float) $allocation['discount_total'] + $taxTotal + $shippingAmount;

        return new CheckoutSummary(
            items: $items,
            subtotal: $this->decimal($subtotal),
            discountTotal: $allocation['discount_total'],
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
            coupon: $coupon ? [
                'id' => $coupon->getKey(),
                'code' => $coupon->code,
                'name' => $coupon->name,
                'type' => $coupon->type->value,
                'value' => $coupon->value,
            ] : null,
            tax: ['total' => $this->decimal($taxTotal)],
            errors: [],
            warnings: $warnings,
        );
    }

    public function eligibleSubtotal(CheckoutValidationResult $validation): string
    {
        return $this->decimal(collect($validation->items)->sum(
            fn ($item): float => (float) $item->product->effectivePrice()
                * (float) $item->cartItem->quantity
        ));
    }

    private function decimal(float|int|string $amount): string
    {
        return number_format((float) $amount, 4, '.', '');
    }
}
