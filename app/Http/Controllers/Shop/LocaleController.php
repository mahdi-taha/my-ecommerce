<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CategoryTranslation;
use App\Models\CmsPageTranslation;
use App\Services\StorefrontContentService;
use App\Services\StorefrontProductListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class LocaleController extends Controller
{
    public function __invoke(
        Request $request,
        string $locale,
        StorefrontProductListingService $listingService,
        StorefrontContentService $contentService,
    ): RedirectResponse {
        abort_unless(in_array($locale, ['en', 'ar'], true), 404);
        $returnTo = (string) $request->input('return_to', '/');

        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/';
        }

        $returnTo = $this->localizedCategoryDestination(
            $returnTo,
            $locale,
            $listingService
        ) ?? $this->localizedCmsDestination($returnTo, $locale, $contentService) ?? $returnTo;
        $request->session()->put('storefront_locale', $locale);

        return redirect()->to($returnTo);
    }

    private function localizedCmsDestination(string $returnTo, string $targetLocale, StorefrontContentService $content): ?string
    {
        try {
            $matched = Route::getRoutes()->match(Request::create($returnTo, 'GET'));
        } catch (Throwable) {
            return null;
        }
        if ($matched->getName() !== 'shop.pages.show') {
            return null;
        }
        $page = $content->pageBySlug((string) $matched->parameter('slug'), app()->getLocale());
        if (! $page) {
            return '/';
        }
        $slug = CmsPageTranslation::query()->where('cms_page_id', $page->id)->where('locale', $targetLocale)->value('slug');
        $query = parse_url($returnTo, PHP_URL_QUERY);

        return filled($slug) ? route('shop.pages.show', $slug, absolute: false).(filled($query) ? '?'.$query : '') : '/';
    }

    private function localizedCategoryDestination(
        string $returnTo,
        string $targetLocale,
        StorefrontProductListingService $listingService
    ): ?string {
        try {
            $matched = Route::getRoutes()->match(Request::create($returnTo, 'GET'));
        } catch (Throwable) {
            return null;
        }

        if ($matched->getName() !== 'shop.categories.show') {
            return null;
        }

        $category = $listingService->categoryBySlug((string) $matched->parameter('slug'));
        if (! $category) {
            return '/';
        }

        $breadcrumbs = $listingService->categoryBreadcrumbs($category);
        $translations = CategoryTranslation::query()
            ->whereIn('category_id', $breadcrumbs->pluck('id'))
            ->where('locale', $targetLocale)
            ->get()
            ->keyBy('category_id');

        if ($breadcrumbs->isEmpty() || $translations->count() !== $breadcrumbs->count()) {
            return '/';
        }

        $slug = $translations->get($category->getKey())?->slug;
        if (! filled($slug)) {
            return '/';
        }

        $query = parse_url($returnTo, PHP_URL_QUERY);

        return route('shop.categories.show', $slug, absolute: false)
            .(filled($query) ? '?'.$query : '');
    }
}
