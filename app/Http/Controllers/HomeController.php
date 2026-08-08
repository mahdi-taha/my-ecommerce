<?php

namespace App\Http\Controllers;

use App\Enums\HomepageBannerPlacement;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tax;
use App\Services\StorefrontContentService;
use App\Services\StorefrontSeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function __construct(
        private StorefrontContentService $content,
        private StorefrontSeoService $seo,
    ) {}

    public function index(): View
    {
        $timestamp = now();
        $homepageCategories = Category::query()
            ->whereNull('parent_id')
            ->where('status', true)
            ->whereHas('translations', fn (Builder $query) => $query
                ->where('locale', app()->getLocale()))
            ->with(['translations' => fn ($query) => $query
                ->where('locale', app()->getLocale())])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->each(function (Category $category): void {
                $category->setAttribute(
                    'homepage_logo_url',
                    filled($category->logo_path) && Storage::disk('public')->exists($category->logo_path)
                        ? Storage::disk('public')->url($category->logo_path)
                        : null
                );
            });

        $baseQuery = Product::query()
            ->active()
            ->visible();
        $baseQuery->withStorefrontCardData(
            app()->getLocale(),
            Auth::guard('customer')->id(),
        );

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

        $onSaleProducts = (clone $baseQuery)
            ->where(function (Builder $query) use ($timestamp): void {
                $query->where(function (Builder $query) use ($timestamp): void {
                    $query->where('type', ProductType::Simple->value)
                        ->whereNull('configurable_id')
                        ->onSale($timestamp)
                        ->where('special_price', '>', 0);
                })->orWhere(function (Builder $query) use ($timestamp): void {
                    $query->where('type', ProductType::Configurable->value)
                        ->whereNull('configurable_id')
                        ->whereHas('variants', fn (Builder $query) => $query
                            ->active()
                            ->where('type', ProductType::Simple->value)
                            ->onSale($timestamp)
                            ->where('special_price', '>', 0));
                });
            })
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->filter(function (Product $product): bool {
                if ($product->type === ProductType::Simple->value) {
                    return $product->hasPositiveEffectivePrice()
                        && $product->hasActiveSpecialPrice();
                }

                return $product->eligibleStorefrontVariants()->contains(
                    fn (Product $variant): bool => $variant->hasActiveSpecialPrice()
                );
            })
            ->take(8)
            ->values();

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
        $homepageContent = $this->content->homepage(app()->getLocale());
        $heroBanners = $homepageContent->where('placement', HomepageBannerPlacement::Hero);
        $heroSideBanners = $homepageContent->where('placement', HomepageBannerPlacement::HeroSide);
        $offerBanners = $homepageContent->where('placement', HomepageBannerPlacement::Offer);
        $homepageServices = $this->content->homepageServices(app()->getLocale());
        $alternateLinks = $this->seo->routeAlternates('shop.home');

        return view('shop.pages.home', compact(
            'allProducts',
            'newProducts',
            'featuredProducts',
            'onSaleProducts',
            'topSellingProducts',
            'currencyCode',
            'taxMode',
            'defaultTax',
            'homepageCategories',
            'heroBanners',
            'heroSideBanners',
            'offerBanners',
            'homepageServices',
            'alternateLinks',
        ));
    }
}
