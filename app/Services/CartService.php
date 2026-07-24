<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CartService
{
    public function __construct(private GuestCartTokenService $tokenService) {}

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

            $product = $this->eligibleSimpleProduct((int) $item->product_id);
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
            ]),
        ]);

        $items = $cart->items
            ->filter(fn (CartItem $item) => $item->product !== null)
            ->map(function (CartItem $item) use ($taxMode, $defaultTax) {
                $unitPrice = $item->product->displayPrice($taxMode, $defaultTax);

                return [
                    'model' => $item,
                    'product' => $item->product,
                    'translation' => $item->product->translations->first(),
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

            $warnings = [];
            $guestItems = CartItem::query()
                ->where('cart_id', $guestCart->getKey())
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($guestItems as $guestItem) {
                if ($guestItem->product_type !== CartItemType::Simple) {
                    throw new RuntimeException('Only Simple Product cart items can currently be merged.');
                }

                $product = $this->eligibleSimpleProductOrNull((int) $guestItem->product_id);

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
                        'product' => $product->translations
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
