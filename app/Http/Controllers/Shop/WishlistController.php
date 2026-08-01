<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWishlistItemRequest;
use App\Models\Product;
use App\Models\Tax;
use App\Models\WishlistItem;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(private WishlistService $wishlistService) {}

    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $items = WishlistItem::query()
            ->whereHas('wishlist', fn ($query) => $query
                ->where('user_id', $request->user('customer')->getKey()))
            ->with([
                'product.translations' => fn ($query) => $query->where('locale', $locale),
                'product.images',
                'product.tax' => fn ($query) => $query->active(),
                'product.inventory',
                'product.variants' => fn ($query) => $query
                    ->active()
                    ->where('type', ProductType::Simple->value)
                    ->with('inventory'),
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate(12);
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTaxId = setting('tax.default_tax_id');
        $defaultTax = $defaultTaxId
            ? Tax::query()->active()->find($defaultTaxId)
            : null;

        return view('shop.pages.wishlist', compact(
            'items',
            'currencyCode',
            'taxMode',
            'defaultTax'
        ));
    }

    public function store(StoreWishlistItemRequest $request): JsonResponse|RedirectResponse
    {
        $this->wishlistService->add(
            $request->user('customer'),
            (int) $request->validated('product_id')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('shop.wishlist.added'),
                'wishlist_count' => $request->user('customer')->wishlist?->items()->count() ?? 0,
                'wishlisted' => true,
            ]);
        }

        return back()->with('success', __('shop.wishlist.added'));
    }

    public function destroy(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->wishlistService->remove($request->user('customer'), $product);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('shop.wishlist.removed'),
                'wishlist_count' => $request->user('customer')->wishlist?->items()->count() ?? 0,
                'wishlisted' => false,
            ]);
        }

        return back()->with('success', __('shop.wishlist.removed'));
    }
}
