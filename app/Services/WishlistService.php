<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WishlistService
{
    public function add(User $customer, int $productId): Wishlist
    {
        return DB::transaction(function () use ($customer, $productId) {
            $lockedCustomer = User::query()
                ->whereKey($customer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureEligibleCustomer($lockedCustomer);

            $product = Product::query()
                ->active()
                ->visible()
                ->whereNull('configurable_id')
                ->whereIn('type', [
                    ProductType::Simple->value,
                    ProductType::Configurable->value,
                ])
                ->find($productId);

            if (! $product) {
                throw ValidationException::withMessages([
                    'product_id' => __('shop.wishlist.product_unavailable'),
                ]);
            }

            $wishlist = Wishlist::query()->firstOrCreate([
                'user_id' => $lockedCustomer->getKey(),
            ]);

            $wishlist->items()->firstOrCreate([
                'product_id' => $product->getKey(),
            ]);

            return $wishlist->fresh();
        });
    }

    public function remove(User $customer, Product $product): void
    {
        DB::transaction(function () use ($customer, $product) {
            $wishlist = Wishlist::query()
                ->where('user_id', $customer->getKey())
                ->lockForUpdate()
                ->first();

            $wishlist?->items()
                ->where('product_id', $product->getKey())
                ->delete();
        });
    }

    private function ensureEligibleCustomer(User $customer): void
    {
        if ($customer->account_type !== AccountType::Customer
            || ! $customer->has_account
            || ! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer' => __('shop.wishlist.customer_unavailable'),
            ]);
        }
    }
}
