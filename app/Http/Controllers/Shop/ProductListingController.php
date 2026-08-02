<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ProductListingRequest;
use App\Models\Category;
use App\Models\Tax;
use App\Services\StorefrontProductListingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProductListingController extends Controller
{
    public function index(
        ProductListingRequest $request,
        StorefrontProductListingService $listingService
    ): View {
        return $this->render($request->validated(), $listingService);
    }

    public function category(
        Request $request,
        string $slug,
        StorefrontProductListingService $listingService
    ): View {
        $input = $request->except('category');
        if (array_key_exists('q', $input)) {
            $input['q'] = trim((string) $input['q']);
        }
        $rules = Arr::except((new ProductListingRequest)->rules(), 'category');
        $filters = validator($input, $rules)->validate();
        $category = $listingService->categoryBySlug($slug);
        abort_unless($category, 404);

        return $this->render($filters, $listingService, $category);
    }

    private function render(
        array $filters,
        StorefrontProductListingService $listingService,
        ?Category $category = null
    ): View {
        $products = $listingService->paginate($filters, app()->getLocale(), $category);
        $categoryTree = $listingService->categoryTree();
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTaxId = setting('tax.default_tax_id');
        $defaultTax = $defaultTaxId ? Tax::query()->active()->find($defaultTaxId) : null;
        $categoryBreadcrumbs = $category ? $listingService->categoryBreadcrumbs($category) : collect();
        $categoryTranslation = $category?->translations->first();
        $categoryBannerUrl = $category && filled($category->banner_path)
            && Storage::disk('public')->exists($category->banner_path)
                ? Storage::disk('public')->url($category->banner_path)
                : null;
        $listingAction = $category
            ? route('shop.categories.show', $categoryTranslation->slug)
            : route('shop.products.index');
        $canonicalUrl = $listingAction;
        if ($category && empty(array_diff_key($filters, array_flip(['page']))) && ($filters['page'] ?? 1) > 1) {
            $canonicalUrl = $listingAction.'?'.http_build_query(['page' => $filters['page']]);
        }

        return view('shop.pages.products', compact(
            'products',
            'filters',
            'categoryTree',
            'currencyCode',
            'taxMode',
            'defaultTax',
            'category',
            'categoryBreadcrumbs',
            'categoryTranslation',
            'categoryBannerUrl',
            'listingAction',
            'canonicalUrl',
        ));
    }
}
