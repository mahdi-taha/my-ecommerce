<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutSummary;
use App\Enums\CartItemType;
use App\Models\ShippingMethod;
use Carbon\CarbonInterface;

class OrderSnapshotFactory
{
    public function __construct(private CheckoutService $checkoutService) {}

    public function make(
        array $customerSnapshot,
        array $validatedItems,
        CheckoutSummary $summary,
        ShippingMethod $shippingMethod,
        array $resolvedAddress,
        string $paymentMethodCode,
        string $locale,
        CarbonInterface $timestamp,
    ): array {
        $summaryItems = collect($summary->items)->keyBy('cart_item_id');

        return [
            'user_id' => $customerSnapshot['user_id'] ?? null,
            'customer_email' => $customerSnapshot['email'] ?? null,
            'customer_first_name' => $customerSnapshot['first_name'],
            'customer_last_name' => $customerSnapshot['last_name'],
            'customer_phone' => $customerSnapshot['phone'] ?? null,
            'locale' => $locale,
            'currency_code' => $summary->currencyCode,
            'payment_method' => $paymentMethodCode,
            'subtotal' => $summary->subtotal,
            'discount_total' => $summary->discountTotal,
            'shipping_total' => $summary->shippingAmount,
            'tax_total' => $summary->taxTotal,
            'grand_total' => $summary->grandTotal,
            'customer_notes' => null,
            'placed_at' => $timestamp,
            'billing_address' => $this->checkoutService->prepareAddressSnapshot(
                $resolvedAddress,
                'billing'
            ),
            'shipping_address' => $this->checkoutService->prepareAddressSnapshot(
                $resolvedAddress,
                'shipping'
            ),
            'shipping' => $this->checkoutService->prepareShippingSnapshot($shippingMethod),
            'items' => collect($validatedItems)->map(function ($validatedItem) use ($summaryItems): array {
                $item = $summaryItems->get($validatedItem->lineId);
                $product = $validatedItem->product;

                return [
                    'product_id' => $product->getKey(),
                    'product_type' => $validatedItem->selectionType === CartItemType::Configurable
                        ? 'variant'
                        : 'simple',
                    'sku' => $product->sku,
                    'product_number' => $product->product_number,
                    'name' => $item['name'],
                    'option_summary' => collect($item['options'])
                        ->map(fn (array $option) => "{$option['attribute_name']}: {$option['option_label']}")
                        ->implode(', ') ?: null,
                    'image_path' => null,
                    'configuration' => null,
                    'quantity' => $item['quantity'],
                    'original_unit_price' => $product->price,
                    'unit_price' => $item['unit_price'],
                    'tax_name' => $item['tax_name'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'row_subtotal' => $item['subtotal'],
                    'discount_amount' => $item['discount_amount'],
                    'row_total' => $item['row_total'],
                    'is_inventory_item' => true,
                    'options' => $item['options'],
                ];
            })->all(),
        ];
    }
}
