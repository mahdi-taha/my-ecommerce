<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ProductListingRequest;
use App\Models\Tax;
use App\Services\StorefrontProductListingService;
use Illuminate\Contracts\View\View;

class ProductListingController extends Controller
{
    public function __invoke(
        ProductListingRequest $request,
        StorefrontProductListingService $listingService
    ): View {
        $filters = $request->validated();
        $products = $listingService->paginate($filters, app()->getLocale());
        $categoryTree = $listingService->categoryTree();
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTaxId = setting('tax.default_tax_id');
        $defaultTax = $defaultTaxId ? Tax::query()->active()->find($defaultTaxId) : null;

        return view('shop.pages.products', compact(
            'products',
            'filters',
            'categoryTree',
            'currencyCode',
            'taxMode',
            'defaultTax',
        ));
    }
}
