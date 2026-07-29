<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;

class CartService
{
    public function __construct(
        private GuestCartTokenService $tokenService,
        private CouponEligibilityService $couponEligibilityService,
    ) {}

    public function resolve(?User $customer, ?string $guestToken): ?Cart
    {
        if (! $customer && ! $guestToken) {
            return null;
        }

        $cart = $customer
            ? Cart::query()->where('user_id', $customer->getKey())->first()
            : $this->guestCartQuery($guestToken)->first();

        if ($cart?->expires_at?->lte(now())) {
            $cart->delete();

            return null;
        }

        return $cart;
    }

    public function quantity(?User $customer, ?string $guestToken): int
    {
        $cart = $this->resolve($customer, $guestToken);

        if (! $cart) {
            return 0;
        }

        return (int) $cart->items()->sum('quantity');
    }

    public function addSimple(
        ?User $customer,
        ?string $guestToken,
        int $productId,
        int $quantity
    ): Cart {
        return DB::transaction(function () use ($customer, $guestToken, $productId, $quantity) {
            $now = now();
            $cart = $this->lockedCartForMutation($customer, $guestToken, $now);
            $product = $this->eligibleSimpleProduct($productId);
            $configurationHash = $this->simpleConfigurationHash($product);
            $item = CartItem::query()
                ->where('cart_id', $cart->getKey())
                ->where('product_id', $product->getKey())
                ->where('configuration_hash', $configurationHash)
                ->lockForUpdate()
                ->first();
            $combinedQuantity = $quantity + (int) ($item?->quantity ?? 0);

            $this->validateAvailableQuantity($product, $combinedQuantity);

            if ($item) {
                $item->update(['quantity' => $combinedQuantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $product->getKey(),
                    'product_type' => CartItemType::Simple->value,
                    'configuration_hash' => $configurationHash,
                    'quantity' => $quantity,
                ]);
            }

            $this->touch($cart, $now);

            return $cart->fresh();
        });
    }

    public function addConfigurable(
        ?User $customer,
        ?string $guestToken,
        int $parentProductId,
        array $selectedOptions,
        int $quantity
    ): Cart {
        return DB::transaction(function () use (
            $customer,
            $guestToken,
            $parentProductId,
            $selectedOptions,
            $quantity
        ) {
            $now = now();
            $cart = $this->lockedCartForMutation($customer, $guestToken, $now);
            $variant = $this->resolveConfigurableVariant(
                $parentProductId,
                $selectedOptions
            );
            $configurationHash = $this->configurableConfigurationHash($variant);
            $item = CartItem::query()
                ->where('cart_id', $cart->getKey())
                ->where('product_id', $variant->getKey())
                ->where('configuration_hash', $configurationHash)
                ->lockForUpdate()
                ->first();
            $combinedQuantity = $quantity + (int) ($item?->quantity ?? 0);

            $this->validateAvailableQuantity($variant, $combinedQuantity);

            if ($item) {
                $item->update(['quantity' => $combinedQuantity]);
            } else {
                $cart->items()->create([
                    'product_id' => $variant->getKey(),
                    'product_type' => CartItemType::Configurable->value,
                    'configuration_hash' => $configurationHash,
                    'quantity' => $quantity,
                ]);
            }

            $this->touch($cart, $now);

            return $cart->fresh();
        });
    }

    public function updateQuantity(
        ?User $customer,
        ?string $guestToken,
        int $cartItemId,
        int|float $quantity
    ): Cart {
        return DB::transaction(function () use ($customer, $guestToken, $cartItemId, $quantity) {
            $now = now();
            $cart = $this->lockedExistingCart($customer, $guestToken);
            $item = $this->lockedOwnedItem($cart, $cartItemId);

            if ($quantity <= 0) {
                $item->delete();
                $this->touch($cart, $now);

                return $cart->fresh();
            }

            if ((float) (int) $quantity !== (float) $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('shop.cart.validation.integer_quantity'),
                ]);
            }

            $product = $this->eligibleCartItemProduct($item);
            $this->validateAvailableQuantity($product, (int) $quantity);

            if ((int) $item->quantity !== (int) $quantity) {
                $item->update(['quantity' => $quantity]);
                $this->touch($cart, $now);
            }

            return $cart->fresh();
        });
    }

    public function remove(
        ?User $customer,
        ?string $guestToken,
        int $cartItemId
    ): Cart {
        return DB::transaction(function () use ($customer, $guestToken, $cartItemId) {
            $now = now();
            $cart = $this->lockedExistingCart($customer, $guestToken);
            $this->lockedOwnedItem($cart, $cartItemId)->delete();
            $this->touch($cart, $now);

            return $cart->fresh();
        });
    }

    public function clear(?User $customer, ?string $guestToken): ?Cart
    {
        return DB::transaction(function () use ($customer, $guestToken) {
            $cart = $this->lockedExistingCart($customer, $guestToken, false);

            if (! $cart || ! $cart->items()->exists()) {
                return $cart;
            }

            $now = now();
            $cart->items()->delete();
            $this->touch($cart, $now);

            return $cart->fresh();
        });
    }

    /**
     * @return array{cart: ?Cart, items: Collection, subtotal: float, currency_code: string, tax_mode: string, default_tax: ?Tax}
     */
    public function summary(?User $customer, ?string $guestToken): array
    {
        $cart = $this->resolve($customer, $guestToken);
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTaxId = setting('tax.default_tax_id');
        $defaultTax = $defaultTaxId
            ? Tax::query()->active()->find($defaultTaxId)
            : null;

        if (! $cart) {
            return [
                'cart' => null,
                'items' => collect(),
                'subtotal' => 0.0,
                'currency_code' => $currencyCode,
                'tax_mode' => $taxMode,
                'default_tax' => $defaultTax,
            ];
        }

        $cart->load([
            'items.product' => fn ($query) => $query->with([
                'translations' => fn ($query) => $query
                    ->where('locale', app()->getLocale()),
                'images',
                'inventory',
                'tax' => fn ($query) => $query->active(),
                'configurable' => fn ($query) => $query->with([
                    'translations' => fn ($query) => $query
                        ->where('locale', app()->getLocale()),
                    'images',
                ]),
                'attributeValues' => fn ($query) => $query
                    ->whereNotNull('attribute_option_id')
                    ->with([
                        'attribute.translations' => fn ($query) => $query
                            ->where('locale', app()->getLocale()),
                        'option.translations' => fn ($query) => $query
                            ->where('locale', app()->getLocale()),
                    ]),
            ]),
        ]);

        $items = $cart->items
            ->filter(fn (CartItem $item) => $item->product !== null)
            ->map(function (CartItem $item) use ($taxMode, $defaultTax) {
                $unitPrice = $item->product->displayPrice($taxMode, $defaultTax);
                $isConfigurable = $item->product_type === CartItemType::Configurable;
                $displayProduct = $isConfigurable
                    ? $item->product->configurable
                    : $item->product;
                $selectedOptions = $isConfigurable
                    ? $item->product->attributeValues
                        ->sortBy('attribute_id')
                        ->map(function ($value) {
                            $attribute = $value->attribute?->translations->first()?->admin_name;
                            $option = $value->option?->translations->first()?->label;

                            return $attribute && $option
                                ? $attribute.': '.$option
                                : null;
                        })
                        ->filter()
                        ->values()
                    : collect();

                return [
                    'model' => $item,
                    'product' => $item->product,
                    'display_product' => $displayProduct,
                    'translation' => $displayProduct?->translations->first(),
                    'selected_options' => $selectedOptions,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * (float) $item->quantity,
                    'available_quantity' => $item->product->inventory?->availableQuantity() ?? '0.0000',
                ];
            })
            ->values();

        return [
            'cart' => $cart,
            'items' => $items,
            'subtotal' => (float) $items->sum('line_total'),
            'currency_code' => $currencyCode,
            'tax_mode' => $taxMode,
            'default_tax' => $defaultTax,
        ];
    }

    /**
     * @return list<string>
     */
    public function mergeGuestCart(User $customer, ?string $guestToken): array
    {
        if (! $guestToken) {
            return [];
        }

        $guestHash = $this->tokenService->hash($guestToken);

        return DB::transaction(function () use ($customer, $guestHash) {
            $this->ensureCustomer($customer);
            $now = now();
            $guestCart = Cart::query()
                ->where('guest_token_hash', $guestHash)
                ->first();

            if (! $guestCart) {
                return [];
            }

            if ($guestCart->expires_at->lte($now)) {
                $guestCart->delete();

                return [];
            }

            $customerCart = Cart::query()
                ->where('user_id', $customer->getKey())
                ->first();

            if ($customerCart?->expires_at?->lte($now)) {
                $customerCart->delete();
                $customerCart = null;
            }

            $customerCart ??= Cart::create([
                'user_id' => $customer->getKey(),
                'guest_token_hash' => null,
                'last_activity_at' => $now,
                'expires_at' => $this->expirationFrom($now),
            ]);

            $lockedCarts = Cart::query()
                ->whereKey([$customerCart->getKey(), $guestCart->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $customerCart = $lockedCarts->get($customerCart->getKey());
            $guestCart = $lockedCarts->get($guestCart->getKey());

            if (! $customerCart || ! $guestCart) {
                throw new RuntimeException('The carts could not be locked for merging.');
            }

            $survivingCouponId = $customerCart->coupon_id ?: $guestCart->coupon_id;

            $warnings = [];
            $guestItems = CartItem::query()
                ->where('cart_id', $guestCart->getKey())
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($guestItems as $guestItem) {
                $product = $this->eligibleCartItemProductOrNull($guestItem);

                if (! $product) {
                    $warnings[] = __('shop.cart.warnings.removed_unavailable');
                    $guestItem->delete();

                    continue;
                }

                $available = (int) floor((float) ($product->inventory?->availableQuantity() ?? 0));
                $customerItem = CartItem::query()
                    ->where('cart_id', $customerCart->getKey())
                    ->where('product_id', $guestItem->product_id)
                    ->where('configuration_hash', $guestItem->configuration_hash)
                    ->lockForUpdate()
                    ->first();
                $combined = (int) ($customerItem?->quantity ?? 0) + (int) $guestItem->quantity;
                $adjusted = min($combined, $available);

                if ($adjusted < $combined) {
                    $warnings[] = __('shop.cart.warnings.quantity_capped', [
                        'product' => ($product->configurable ?? $product)
                            ->translations
                            ->firstWhere('locale', app()->getLocale())?->name
                            ?? $product->sku,
                        'quantity' => $adjusted,
                    ]);
                }

                if ($adjusted <= 0) {
                    $guestItem->delete();

                    continue;
                }

                if ($customerItem) {
                    $customerItem->update(['quantity' => $adjusted]);
                    $guestItem->delete();
                } else {
                    $guestItem->update([
                        'cart_id' => $customerCart->getKey(),
                        'quantity' => $adjusted,
                    ]);
                }
            }

            $customerCart->coupon_id = $survivingCouponId;

            if ($survivingCouponId) {
                $coupon = Coupon::query()->whereKey($survivingCouponId)->lockForUpdate()->first();
                $customerCart->load([
                    'items.product',
                ]);
                $eligibleSubtotal = number_format(
                    $customerCart->items->sum(
                        fn (CartItem $item): float => (float) $item->product?->effectivePrice()
                            * (float) $item->quantity
                    ),
                    4,
                    '.',
                    ''
                );
                $couponErrors = $coupon
                    ? $this->couponEligibilityService->validate($coupon, $eligibleSubtotal, $customer)
                    : ['coupon_not_found'];

                if ($couponErrors !== []) {
                    $customerCart->coupon_id = null;
                    $warnings[] = __('shop.cart.warnings.coupon_removed');
                }
            }

            $this->touch($customerCart, $now);
            $guestCart->delete();

            return array_values(array_unique($warnings));
        });
    }

    public function pruneExpired(int $limit = 500): int
    {
        $ids = Cart::query()
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        return $ids->isEmpty()
            ? 0
            : Cart::query()->whereKey($ids)->delete();
    }

    public function clearForCheckout(Cart $cart, mixed $timestamp): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Checkout Cart clearing requires an active database transaction.');
        }

        $cart->items()->delete();
        $cart->coupon_id = null;
        $this->touch($cart, $timestamp);
    }

    private function lockedCartForMutation(
        ?User $customer,
        ?string $guestToken,
        mixed $now
    ): Cart {
        if ($customer) {
            $this->ensureCustomer($customer);
        } elseif (! $guestToken) {
            throw new RuntimeException('A guest cart token is required.');
        }

        $cart = $customer
            ? Cart::query()->where('user_id', $customer->getKey())->lockForUpdate()->first()
            : $this->guestCartQuery($guestToken)->lockForUpdate()->first();

        if ($cart?->expires_at?->lte($now)) {
            $cart->delete();
            $cart = null;
        }

        if ($cart) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $customer?->getKey(),
            'guest_token_hash' => $customer
                ? null
                : $this->tokenService->hash($guestToken),
            'last_activity_at' => $now,
            'expires_at' => $this->expirationFrom($now),
        ]);
    }

    private function lockedExistingCart(
        ?User $customer,
        ?string $guestToken,
        bool $required = true
    ): ?Cart {
        $cart = $customer
            ? Cart::query()->where('user_id', $customer->getKey())->lockForUpdate()->first()
            : $this->guestCartQuery($guestToken)->lockForUpdate()->first();

        if ($cart?->expires_at?->lte(now())) {
            $cart->delete();
            $cart = null;
        }

        if (! $cart && $required) {
            throw ValidationException::withMessages([
                'cart' => __('shop.cart.validation.not_found'),
            ]);
        }

        return $cart;
    }

    private function lockedOwnedItem(Cart $cart, int $cartItemId): CartItem
    {
        $item = CartItem::query()
            ->whereKey($cartItemId)
            ->where('cart_id', $cart->getKey())
            ->lockForUpdate()
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'cart' => __('shop.cart.validation.item_not_found'),
            ]);
        }

        return $item;
    }

    private function eligibleSimpleProduct(int $productId): Product
    {
        $product = $this->eligibleSimpleProductQuery($productId)->first();

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => __('shop.cart.validation.ineligible_product'),
            ]);
        }

        return $product;
    }

    private function eligibleSimpleProductOrNull(int $productId): ?Product
    {
        return $this->eligibleSimpleProductQuery($productId)
            ->with([
                'translations' => fn ($query) => $query
                    ->where('locale', app()->getLocale()),
            ])
            ->first();
    }

    private function eligibleCartItemProduct(CartItem $item): Product
    {
        if ($item->product_type === CartItemType::Simple) {
            return $this->eligibleSimpleProduct((int) $item->product_id);
        }

        if ($item->product_type === CartItemType::Configurable) {
            $variant = $this->eligibleConfigurableVariantQuery(
                (int) $item->product_id
            )->first();

            if ($variant) {
                return $variant;
            }
        }

        throw ValidationException::withMessages([
            'product_id' => __('shop.cart.validation.ineligible_product'),
        ]);
    }

    private function eligibleCartItemProductOrNull(CartItem $item): ?Product
    {
        $query = match ($item->product_type) {
            CartItemType::Simple => $this->eligibleSimpleProductQuery(
                (int) $item->product_id
            ),
            CartItemType::Configurable => $this->eligibleConfigurableVariantQuery(
                (int) $item->product_id
            ),
        };

        return $query
            ->with([
                'translations' => fn ($query) => $query
                    ->where('locale', app()->getLocale()),
                'configurable.translations' => fn ($query) => $query
                    ->where('locale', app()->getLocale()),
            ])
            ->first();
    }

    private function eligibleSimpleProductQuery(int $productId): Builder
    {
        return Product::query()
            ->whereKey($productId)
            ->active()
            ->visible()
            ->where('type', ProductType::Simple->value)
            ->whereNull('configurable_id')
            ->with('inventory');
    }

    private function eligibleConfigurableVariantQuery(int $productId): Builder
    {
        return Product::query()
            ->whereKey($productId)
            ->active()
            ->where('type', ProductType::Simple->value)
            ->whereNotNull('configurable_id')
            ->whereHas('configurable', fn (Builder $query) => $query
                ->active()
                ->visible()
                ->where('type', ProductType::Configurable->value)
                ->whereNull('configurable_id'))
            ->with(['inventory', 'configurable']);
    }

    private function resolveConfigurableVariant(
        int $parentProductId,
        array $selectedOptions
    ): Product {
        $parent = Product::query()
            ->whereKey($parentProductId)
            ->active()
            ->visible()
            ->where('type', ProductType::Configurable->value)
            ->whereNull('configurable_id')
            ->lockForUpdate()
            ->first();

        if (! $parent) {
            throw ValidationException::withMessages([
                'product_id' => __('shop.cart.validation.ineligible_product'),
            ]);
        }

        $superAttributes = $parent->superAttributes()
            ->with('options:id')
            ->get()
            ->keyBy('attribute_id');
        $normalized = [];

        foreach ($selectedOptions as $attributeId => $optionId) {
            if (! ctype_digit((string) $attributeId)) {
                throw ValidationException::withMessages([
                    'options' => __('shop.cart.validation.invalid_configuration'),
                ]);
            }

            $normalized[(int) $attributeId] = (int) $optionId;
        }

        ksort($normalized, SORT_NUMERIC);
        $requiredAttributeIds = $superAttributes->keys()
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if (array_keys($normalized) !== $requiredAttributeIds) {
            throw ValidationException::withMessages([
                'options' => __('shop.cart.validation.complete_configuration'),
            ]);
        }

        foreach ($normalized as $attributeId => $optionId) {
            $allowed = $superAttributes
                ->get($attributeId)
                ?->options
                ->contains('id', $optionId);

            if (! $allowed) {
                throw ValidationException::withMessages([
                    'options.'.$attributeId => __('shop.cart.validation.invalid_option'),
                ]);
            }
        }

        $variants = Product::query()
            ->where('configurable_id', $parent->getKey())
            ->where('type', ProductType::Simple->value)
            ->active()
            ->with('attributeValues')
            ->lockForUpdate()
            ->get()
            ->filter(function (Product $variant) use ($normalized) {
                $values = $variant->attributeValues
                    ->whereNotNull('attribute_option_id');
                $combination = $values
                    ->pluck('attribute_option_id', 'attribute_id')
                    ->map(fn ($id) => (int) $id)
                    ->sortKeys()
                    ->all();

                return $values->count() === count($normalized)
                    && $combination === $normalized;
            })
            ->values();

        if ($variants->count() !== 1) {
            throw ValidationException::withMessages([
                'options' => __('shop.cart.validation.unavailable_configuration'),
            ]);
        }

        return $variants->first()->load('inventory');
    }

    private function validateAvailableQuantity(Product $product, int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('shop.cart.validation.minimum_quantity'),
            ]);
        }

        $available = (float) ($product->inventory?->availableQuantity() ?? 0);

        if ($available <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('shop.cart.validation.out_of_stock'),
            ]);
        }

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                'quantity' => __('shop.cart.validation.insufficient_stock', [
                    'quantity' => rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'),
                ]),
            ]);
        }
    }

    private function simpleConfigurationHash(Product $product): string
    {
        return hash('sha256', json_encode([
            'type' => CartItemType::Simple->value,
            'product_id' => $product->getKey(),
        ], JSON_THROW_ON_ERROR));
    }

    private function configurableConfigurationHash(Product $variant): string
    {
        return hash('sha256', json_encode([
            'type' => CartItemType::Configurable->value,
            'product_id' => $variant->getKey(),
        ], JSON_THROW_ON_ERROR));
    }

    private function guestCartQuery(?string $guestToken): Builder
    {
        $query = Cart::query()->whereRaw('1 = 0');

        if ($guestToken) {
            $query = Cart::query()->where(
                'guest_token_hash',
                $this->tokenService->hash($guestToken)
            );
        }

        return $query;
    }

    private function ensureCustomer(User $customer): void
    {
        if ($customer->account_type !== AccountType::Customer
            || ! $customer->has_account
            || ! $customer->is_active) {
            throw ValidationException::withMessages([
                'cart' => __('shop.cart.validation.invalid_customer'),
            ]);
        }
    }

    private function touch(Cart $cart, mixed $now): void
    {
        $cart->update([
            'last_activity_at' => $now,
            'expires_at' => $this->expirationFrom($now),
        ]);
    }

    private function expirationFrom(mixed $timestamp): mixed
    {
        $days = max(1, (int) setting('cart.lifetime_days', 30));

        return $timestamp->copy()->addDays($days);
    }
}
