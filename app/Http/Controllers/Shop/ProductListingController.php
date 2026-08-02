<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ProductListingRequest;
use App\Models\Category;
use App\Models\Tax;
use App\Services\StorefrontProductListingService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductListingController extends Controller
{
    public function index(
        ProductListingRequest $request,
        StorefrontProductListingService $listingService
    ): View|RedirectResponse {
        $filters = $request->validated();
        if (isset($filters['category'])) {
            $category = $listingService->categoryById((int) $filters['category']);
            if (! $category) {
                throw ValidationException::withMessages(['category' => __('validation.exists', ['attribute' => 'category'])]);
            }

            return redirect()->route('shop.categories.show', [
                'slug' => $category->translations->first()->slug,
                ...Arr::except($filters, 'category'),
            ]);
        }

        return $this->render($filters, $listingService);
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
        $at = now();
        $facets = $listingService->categoryFacets($category, app()->getLocale(), $at);
        $filters['_attribute_filters'] = $listingService->validateAttributeFilters(
            $filters['attributes'] ?? [],
            $facets
        );

        return $this->render($filters, $listingService, $category, $facets, $at);
    }

    private function render(
        array $filters,
        StorefrontProductListingService $listingService,
        ?Category $category = null,
        array $attributeFacets = [],
        ?CarbonInterface $at = null
    ): View {
        $products = $listingService->paginate($filters, app()->getLocale(), $category, $at);
        $currencyCode = setting('currency.default_currency', 'USD');
        $taxMode = setting('tax.tax_mode', 'b2c');
        $defaultTaxId = setting('tax.default_tax_id');
        $defaultTax = $defaultTaxId ? Tax::query()->active()->find($defaultTaxId) : null;
        $categoryBreadcrumbs = $category ? $listingService->categoryBreadcrumbs($category) : collect();
        $categoryTranslation = $category?->translations->first();
        $categoryBannerPath = trim((string) $category?->banner_path);
        $categoryBannerUrl = $categoryBannerPath !== ''
            && Storage::disk('public')->exists($categoryBannerPath)
                ? Storage::disk('public')->url($categoryBannerPath)
                : null;
        $listingAction = $category
            ? route('shop.categories.show', $categoryTranslation->slug)
            : route('shop.products.index');
        $canonicalUrl = $listingAction;
        $publicFilters = Arr::except($filters, '_attribute_filters');
        if (empty(array_diff_key($publicFilters, array_flip(['page']))) && ($filters['page'] ?? 1) > 1) {
            $canonicalUrl = $listingAction.'?'.http_build_query(['page' => $filters['page']]);
        }

        return view('shop.pages.products', compact(
            'products',
            'filters',
            'currencyCode',
            'taxMode',
            'defaultTax',
            'category',
            'categoryBreadcrumbs',
            'categoryTranslation',
            'categoryBannerUrl',
            'listingAction',
            'canonicalUrl',
            'attributeFacets',
        ));
    }
}
