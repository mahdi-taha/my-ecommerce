<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index(): View
    {
        $baseQuery = Product::query()
            ->active()
            ->visible()
            ->with([
                'translations' => fn ($query) => $query->where('locale', app()->getLocale()),
                'images',
                'inventory',
                'superAttributes',
                'variants' => fn ($query) => $query
                    ->active()
                    ->where('type', ProductType::Simple->value)
                    ->with([
                        'attributeValues',
                        'tax' => fn ($query) => $query->active(),
                    ]),
                'tax' => fn ($query) => $query->active(),
                'categories' => fn ($query) => $query
                    ->where('status', true)
                    ->with([
                        'translations' => fn ($query) => $query->where('locale', app()->getLocale()),
                    ]),
            ]);

        if ($customerId = Auth::guard('customer')->id()) {
            $baseQuery->withExists([
                'wishlistItems as is_wishlisted' => fn (Builder $query) => $query
                    ->whereHas('wishlist', fn (Builder $query) => $query
                        ->where('user_id', $customerId)),
            ]);
        }

        $allProducts = (clone $baseQuery)
            ->latest()
            ->limit(8)
            ->get();

        $newProducts = (clone $baseQuery)
            ->where('is_new', true)
            ->latest()
            ->limit(8)
            ->get();

        $featuredProducts = (clone $baseQuery)
            ->where('is_featured', true)
            ->latest()
            ->limit(8)
            ->get();

        $topSellingProducts = (clone $baseQuery)
            ->withSum([
                'orderItems as sold_quantity' => fn (Builder $query) => $query
                    ->whereHas('order', fn (Builder $query) => $query
                        ->where('status', OrderStatus::Completed->value)),
            ], 'quantity')
            ->orderByDesc('sold_quantity')
            ->latest('products.created_at')
            ->limit(8)
            ->get();

        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTaxId = setting('tax.default_tax_id');
        $defaultTax = $defaultTaxId
            ? Tax::query()->active()->find($defaultTaxId)
            : null;

        return view('shop.pages.home', compact(
            'allProducts',
            'newProducts',
            'featuredProducts',
            'topSellingProducts',
            'currencyCode',
            'taxMode',
            'defaultTax',
        ));
    }
}
