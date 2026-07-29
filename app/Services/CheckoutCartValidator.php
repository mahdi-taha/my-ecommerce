<?php

namespace App\Services;

use App\DTOs\Checkout\CheckoutValidationError;
use App\DTOs\Checkout\CheckoutValidationResult;
use App\DTOs\Checkout\ValidatedCheckoutItem;
use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use Illuminate\Support\Collection;

class CheckoutCartValidator
{
    public function validate(
        Cart $cart,
        string $shippingMethodCode,
        string $paymentMethodCode
    ): CheckoutValidationResult {
        $locale = app()->getLocale();

        $cart->load([
            'items.product' => fn ($query) => $query->with([
                'inventory',
                'tax' => fn ($query) => $query->active(),
                'translations' => fn ($query) => $query->where('locale', $locale),
                'configurable' => fn ($query) => $query->with([
                    'translations' => fn ($query) => $query->where('locale', $locale),
                    'superAttributes.attribute.translations' => fn ($query) => $query
                        ->where('locale', $locale),
                ]),
                'attributeValues' => fn ($query) => $query
                    ->whereNotNull('attribute_option_id')
                    ->with([
                        'attribute.translations' => fn ($query) => $query
                            ->where('locale', $locale),
                        'option.translations' => fn ($query) => $query
                            ->where('locale', $locale),
                    ]),
            ]),
        ]);

        $shippingMethod = ShippingMethod::query()
            ->where('code', $shippingMethodCode)
            ->where('is_active', true)
            ->first();
        $paymentMethod = PaymentMethod::query()
            ->where('code', $paymentMethodCode)
            ->where('is_active', true)
            ->first();

        return $this->validateLoadedItems(
            $cart->items,
            $shippingMethod,
            $paymentMethod
        );
    }

    public function validateLoadedItems(
        Collection $items,
        ?ShippingMethod $shippingMethod,
        ?PaymentMethod $paymentMethod
    ): CheckoutValidationResult {
        $validatedItems = [];
        $errors = [];

        if ($items->isEmpty()) {
            $errors[] = $this->error('empty_cart', 'cart', 'The Cart is empty.');
        }

        if (! $shippingMethod) {
            $errors[] = $this->error(
                'shipping_method_unavailable',
                'shipping_method',
                'The selected Shipping Method is unavailable.'
            );
        }

        if (! $paymentMethod) {
            $errors[] = $this->error(
                'payment_method_unavailable',
                'payment_method',
                'The selected Payment Method is unavailable.'
            );
        }

        foreach ($items as $item) {
            $itemErrors = $this->validateItem($item);

            if ($itemErrors !== []) {
                array_push($errors, ...$itemErrors);

                continue;
            }

            $product = $item->product;
            $displayProduct = $item->product_type === CartItemType::Configurable
                ? $product->configurable
                : $product;
            $optionSnapshots = $item->product_type === CartItemType::Configurable
                ? $this->optionSnapshots($product)
                : [];

            $validatedItems[] = new ValidatedCheckoutItem(
                cartItem: $item,
                product: $product,
                displayProduct: $displayProduct,
                optionSnapshots: $optionSnapshots,
                availableQuantity: $product->inventory?->availableQuantity() ?? '0.0000',
            );
        }

        return new CheckoutValidationResult(
            items: $validatedItems,
            shippingMethod: $shippingMethod,
            paymentMethod: $paymentMethod,
            errors: $errors,
        );
    }

    private function validateItem(CartItem $item): array
    {
        $product = $item->product;

        if (! $product) {
            return [$this->itemError(
                'product_unavailable',
                $item,
                'The Cart Product is no longer available.'
            )];
        }

        if (! $product->status) {
            return [$this->itemError(
                'product_inactive',
                $item,
                'The Cart Product is inactive.'
            )];
        }

        $configurationErrors = match ($item->product_type) {
            CartItemType::Simple => $this->validateSimpleProduct($item, $product),
            CartItemType::Configurable => $this->validateConfigurableProduct($item, $product),
        };

        if ($configurationErrors !== []) {
            return $configurationErrors;
        }

        $quantity = (float) $item->quantity;

        if ($quantity <= 0) {
            return [$this->itemError(
                'invalid_quantity',
                $item,
                'The Cart quantity must be greater than zero.'
            )];
        }

        $available = (float) ($product->inventory?->availableQuantity() ?? 0);

        if ($quantity > $available) {
            return [$this->itemError(
                'insufficient_stock',
                $item,
                'The requested quantity exceeds the available inventory.'
            )];
        }

        return [];
    }

    private function validateSimpleProduct(CartItem $item, Product $product): array
    {
        if ($product->type !== ProductType::Simple->value || $product->configurable_id !== null) {
            return [$this->itemError(
                'product_unavailable',
                $item,
                'The Cart Product is not an eligible standalone Simple Product.'
            )];
        }

        if (! $product->is_visible_individually) {
            return [$this->itemError(
                'product_not_visible',
                $item,
                'The Cart Product is not storefront-visible.'
            )];
        }

        return [];
    }

    private function validateConfigurableProduct(CartItem $item, Product $variant): array
    {
        if ($variant->type !== ProductType::Simple->value || $variant->configurable_id === null) {
            return [$this->itemError(
                'invalid_configuration',
                $item,
                'The selected Configurable Product variant is invalid.'
            )];
        }

        $parent = $variant->configurable;

        if (! $parent) {
            return [$this->itemError(
                'product_unavailable',
                $item,
                'The Configurable Product is no longer available.'
            )];
        }

        if (! $parent->status) {
            return [$this->itemError(
                'product_inactive',
                $item,
                'The Configurable Product is inactive.'
            )];
        }

        if ($parent->type !== ProductType::Configurable->value || $parent->configurable_id !== null) {
            return [$this->itemError(
                'invalid_configuration',
                $item,
                'The selected Configurable Product parent is invalid.'
            )];
        }

        if (! $parent->is_visible_individually) {
            return [$this->itemError(
                'product_not_visible',
                $item,
                'The Configurable Product is not storefront-visible.'
            )];
        }

        $requiredAttributeIds = $parent->superAttributes
            ->pluck('attribute_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();
        $values = $variant->attributeValues->whereNotNull('attribute_option_id');
        $selectedAttributeIds = $values
            ->pluck('attribute_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();
        $complete = $requiredAttributeIds->isNotEmpty()
            && $requiredAttributeIds->all() === $selectedAttributeIds->all()
            && $values->every(fn ($value) => $value->attribute
                && $value->option
                && $value->attribute->translations->isNotEmpty()
                && $value->option->translations->isNotEmpty());

        if (! $complete) {
            return [$this->itemError(
                'invalid_configuration',
                $item,
                'The selected Configurable Product options are incomplete.'
            )];
        }

        return [];
    }

    private function optionSnapshots(Product $variant): array
    {
        return CheckoutService::prepareOptionSnapshots(
            $variant->attributeValues->map(fn ($value) => [
                'attribute_code' => $value->attribute->code,
                'attribute_name' => $value->attribute->translations->first()->admin_name,
                'option_code' => $value->option->code,
                'option_label' => $value->option->translations->first()->label,
            ])->all()
        );
    }

    private function error(string $code, string $field, string $message): CheckoutValidationError
    {
        return new CheckoutValidationError($code, $field, $message);
    }

    private function itemError(
        string $code,
        CartItem $item,
        string $message
    ): CheckoutValidationError {
        return new CheckoutValidationError(
            code: $code,
            field: 'items',
            message: $message,
            cartItemId: $item->getKey(),
            productId: $item->product_id ? (int) $item->product_id : null,
        );
    }
}
