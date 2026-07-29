<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutOrderPlacementResult;
use App\DTOs\Checkout\CheckoutValidationError;
use App\Enums\CartItemType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutOrderPlacementService
{
    public function __construct(
        private CheckoutCartValidator $cartValidator,
        private CheckoutService $checkoutService,
        private OrderService $orderService,
        private CartService $cartService,
        private GuestCartTokenService $guestCartTokenService,
        private CheckoutAddressResolver $addressResolver,
    ) {}

    public function place(
        Cart $cart,
        array $checkoutData,
        ?User $customer = null,
        ?string $guestToken = null
    ): CheckoutOrderPlacementResult {
        $expectedSignature = $this->cartSignature($cart->getKey());

        return DB::transaction(function () use (
            $cart,
            $checkoutData,
            $customer,
            $guestToken,
            $expectedSignature
        ): CheckoutOrderPlacementResult {
            $timestamp = now();
            $lockedCart = Cart::query()->lockForUpdate()->find($cart->getKey());

            if (! $lockedCart) {
                return $this->failure('cart_changed', 'cart', 'The Cart changed before Checkout could complete.');
            }

            if (! $this->ownsCart($lockedCart, $customer, $guestToken)) {
                return $this->failure('cart_ownership_mismatch', 'cart', 'The Cart does not belong to the current customer.');
            }

            if (! $customer && ! $this->guestCheckoutAllowed()) {
                return $this->failure('guest_checkout_disabled', 'customer', 'Guest Checkout is disabled.');
            }

            try {
                $resolvedAddress = $this->addressResolver->resolve($checkoutData, $customer);
            } catch (ValidationException $exception) {
                $errors = $exception->errors();
                $field = (string) array_key_first($errors);
                $message = $errors[$field][0] ?? __('shop.checkout.failures.order_placement_failed');
                $code = $field === 'customer' ? 'customer_unavailable' : 'address_unavailable';

                return $this->failure($code, $field ?: 'address', $message);
            }

            $items = CartItem::query()
                ->where('cart_id', $lockedCart->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                return $this->failure('empty_cart', 'cart', 'The Cart is empty.');
            }

            $productReferences = Product::query()
                ->whereIn('id', $items->pluck('product_id'))
                ->get(['id', 'configurable_id']);
            $productIds = $items->pluck('product_id')
                ->merge($productReferences->pluck('configurable_id')->filter())
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($this->signatureFor($items, $products) !== $expectedSignature) {
                return $this->failure('cart_changed', 'cart', 'The Cart changed before Checkout could complete.');
            }

            $inventories = ProductInventory::query()
                ->whereIn('product_id', $items->pluck('product_id'))
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();
            $shippingMethod = $this->lockedShippingMethod($checkoutData['shipping_method'] ?? null);
            $paymentMethod = $this->lockedPaymentMethod($checkoutData['payment_method'] ?? null);
            $validation = $this->cartValidator->validateLockedItems(
                $items,
                $products,
                $inventories,
                $shippingMethod,
                $paymentMethod
            );

            if (! $validation->isValid()) {
                return CheckoutOrderPlacementResult::failures($validation->errors);
            }

            $summary = $this->checkoutService->summarizeLocked($validation);

            if (! $summary->isValid()) {
                return CheckoutOrderPlacementResult::failures(
                    array_map(
                        fn (array $error) => new CheckoutValidationError(
                            $error['code'],
                            $error['field'],
                            $error['message'],
                            $error['cart_item_id'],
                            $error['product_id'],
                        ),
                        $summary->errors
                    )
                );
            }

            $order = $this->orderService->createWithinTransaction(
                $this->orderData(
                    $checkoutData,
                    $customer,
                    $validation->items,
                    $summary,
                    $shippingMethod,
                    $resolvedAddress,
                    $timestamp
                )
            );

            $this->cartService->clearForCheckout($lockedCart, $timestamp);

            return CheckoutOrderPlacementResult::success(
                $order->load(['addresses', 'shipping', 'items.options', 'payment', 'statusHistory'])
            );
        });
    }

    private function ownsCart(Cart $cart, ?User $customer, ?string $guestToken): bool
    {
        if ($customer) {
            return (int) $cart->user_id === (int) $customer->getKey()
                && $cart->guest_token_hash === null;
        }

        if ($cart->user_id !== null || ! $guestToken || ! $cart->guest_token_hash) {
            return false;
        }

        return hash_equals(
            $cart->guest_token_hash,
            $this->guestCartTokenService->hash($guestToken)
        );
    }

    private function guestCheckoutAllowed(): bool
    {
        return filter_var(
            setting('checkout.allow_guest_checkout', true),
            FILTER_VALIDATE_BOOL
        );
    }

    private function cartSignature(int $cartId): string
    {
        $items = CartItem::query()
            ->where('cart_id', $cartId)
            ->orderBy('id')
            ->get();
        $products = Product::query()
            ->whereIn('id', $items->pluck('product_id'))
            ->get(['id', 'configurable_id']);

        return $this->signatureFor($items, $products);
    }

    private function signatureFor(Collection $items, Collection $products): string
    {
        $parents = $products->keyBy(fn (Product $product) => (int) $product->getKey());
        $signature = $items->sortBy('id')->map(fn (CartItem $item) => [
            'id' => (int) $item->getKey(),
            'product_id' => (int) $item->product_id,
            'product_type' => $item->product_type instanceof CartItemType
                ? $item->product_type->value
                : (string) $item->product_type,
            'quantity' => number_format((float) $item->quantity, 4, '.', ''),
            'configuration_hash' => $item->configuration_hash,
            'configurable_id' => $parents->get((int) $item->product_id)?->configurable_id,
        ])->values()->all();

        return hash('sha256', json_encode($signature, JSON_THROW_ON_ERROR));
    }

    private function lockedShippingMethod(mixed $code): ?ShippingMethod
    {
        return is_string($code)
            ? ShippingMethod::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first()
            : null;
    }

    private function lockedPaymentMethod(mixed $code): ?PaymentMethod
    {
        return is_string($code)
            ? PaymentMethod::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first()
            : null;
    }

    private function orderData(
        array $checkoutData,
        ?User $customer,
        array $validatedItems,
        object $summary,
        ShippingMethod $shippingMethod,
        array $resolvedAddress,
        mixed $timestamp
    ): array {
        $summaryItems = collect($summary->items)->keyBy('cart_item_id');

        return [
            'user_id' => $customer?->getKey(),
            'customer_email' => $checkoutData['customer']['email'] ?? $customer?->email ?? '',
            'customer_first_name' => $checkoutData['customer']['first_name'],
            'customer_last_name' => $checkoutData['customer']['last_name'],
            'customer_phone' => $checkoutData['customer']['phone'] ?? null,
            'locale' => app()->getLocale(),
            'currency_code' => $summary->currencyCode,
            'payment_method' => $checkoutData['payment_method'],
            'subtotal' => $summary->subtotal,
            'discount_total' => '0.0000',
            'shipping_total' => $summary->shippingAmount,
            'tax_total' => $summary->taxTotal,
            'grand_total' => $summary->grandTotal,
            'customer_notes' => null,
            'admin_notes' => null,
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
                $item = $summaryItems->get($validatedItem->cartItem->getKey());
                $product = $validatedItem->product;

                return [
                    'product_id' => $product->getKey(),
                    'product_type' => $validatedItem->cartItem->product_type === CartItemType::Configurable
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
                    'row_total' => $item['row_total'],
                    'is_inventory_item' => true,
                    'options' => $item['options'],
                ];
            })->all(),
        ];
    }

    private function failure(string $code, string $field, string $message): CheckoutOrderPlacementResult
    {
        return CheckoutOrderPlacementResult::failure(
            new CheckoutValidationError($code, $field, $message)
        );
    }
}
