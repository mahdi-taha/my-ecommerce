<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutOrderPlacementResult;
use App\DTOs\Checkout\CheckoutValidationError;
use App\Enums\CartItemType;
use App\Enums\NotificationEventCode;
use App\Events\CommerceEventOccurred;
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
        private CouponCartService $couponCartService,
        private CouponUsageService $couponUsageService,
        private OrderSnapshotFactory $orderSnapshotFactory,
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

            $summary = $this->checkoutService->summarizeLocked($validation, $lockedCart, false);

            if (! $summary->isValid()) {
                if (collect($summary->errors)->contains('code', 'coupon_invalid')) {
                    $this->couponCartService->remove($lockedCart);
                }

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

            try {
                // This savepoint rolls back a speculative Order aggregate while allowing
                // the outer transaction to commit removal of a newly invalid Coupon.
                $order = DB::transaction(function () use (
                    $checkoutData,
                    $customer,
                    $validation,
                    $summary,
                    $shippingMethod,
                    $resolvedAddress,
                    $timestamp
                ) {
                    $order = $this->orderService->createWithinTransaction(
                        $this->orderSnapshotFactory->make(
                            customerSnapshot: [
                                'user_id' => $customer?->getKey(),
                                'email' => $checkoutData['customer']['email'] ?? $customer?->email,
                                'first_name' => $checkoutData['customer']['first_name'],
                                'last_name' => $checkoutData['customer']['last_name'],
                                'phone' => $checkoutData['customer']['phone'] ?? null,
                            ],
                            validatedItems: $validation->items,
                            summary: $summary,
                            shippingMethod: $shippingMethod,
                            resolvedAddress: $resolvedAddress,
                            paymentMethodCode: $checkoutData['payment_method'],
                            locale: app()->getLocale(),
                            timestamp: $timestamp,
                        )
                    );

                    if (! $summary->coupon) {
                        return $order;
                    }

                    $this->couponUsageService->create(
                        (int) $summary->coupon['id'],
                        $order,
                        $summary->subtotal,
                        $summary->discountTotal,
                        $customer
                    );

                    return $order;
                });
            } catch (ValidationException $exception) {
                if (! array_key_exists('coupon', $exception->errors())) {
                    throw $exception;
                }

                $this->couponCartService->remove($lockedCart);

                return $this->failure(
                    'coupon_invalid',
                    'coupon',
                    __('shop.checkout.coupon.invalid_removed')
                );
            }

            $this->cartService->clearForCheckout($lockedCart, $timestamp);

            CommerceEventOccurred::dispatch(
                NotificationEventCode::OrderPlaced,
                'order',
                (int) $order->getKey()
            );

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

    private function failure(string $code, string $field, string $message): CheckoutOrderPlacementResult
    {
        return CheckoutOrderPlacementResult::failure(
            new CheckoutValidationError($code, $field, $message)
        );
    }
}
