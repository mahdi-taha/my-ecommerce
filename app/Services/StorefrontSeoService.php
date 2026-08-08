<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\ProductTranslation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use LogicException;

class StorefrontSeoService
{
    public function __construct(
        private StorefrontProductListingService $products,
        private StorefrontContentService $content,
    ) {}

    /** @return array<int, array{hreflang: string, href: string}> */
    public function routeAlternates(string $routeName, array $parameters = []): array
    {
        return $this->withDefault(collect(StorefrontLocaleUrlService::LOCALES)
            ->mapWithKeys(fn (string $locale): array => [
                $locale => route($routeName, ['locale' => $locale, ...$parameters]),
            ]));
    }

    /** @return array<int, array{hreflang: string, href: string}> */
    public function productAlternates(int $productId): array
    {
        $translations = ProductTranslation::query()
            ->where('product_id', $productId)
            ->whereIn('locale', StorefrontLocaleUrlService::LOCALES)
            ->get()
            ->keyBy('locale');
        $urls = collect();
        foreach (StorefrontLocaleUrlService::LOCALES as $locale) {
            $translation = $translations->get($locale);
            if ($translation && $this->products->productIsEligible($productId, $locale)) {
                $urls->put($locale, route('shop.products.show', [
                    'locale' => $locale,
                    'url_key' => $translation->url_key,
                ]));
            }
        }

        return $this->withDefault($urls);
    }

    /** @return array<int, array{hreflang: string, href: string}> */
    public function categoryAlternates(int $categoryId, array $parameters = []): array
    {
        $categories = Category::query()
            ->with(['translations' => fn ($query) => $query
                ->whereIn('locale', StorefrontLocaleUrlService::LOCALES)])
            ->get()
            ->keyBy('id');
        $urls = collect();
        foreach (StorefrontLocaleUrlService::LOCALES as $locale) {
            $category = $this->reachableCategories($categories, $locale)->firstWhere('id', $categoryId);
            $translation = $category?->translations->firstWhere('locale', $locale);
            if ($translation) {
                $urls->put($locale, route('shop.categories.show', [
                    'locale' => $locale,
                    'slug' => $translation->slug,
                    ...$parameters,
                ]));
            }
        }

        return $this->withDefault($urls);
    }

    /** @return array<int, array{hreflang: string, href: string}> */
    public function cmsAlternates(int $pageId): array
    {
        $page = CmsPage::query()->active()->with(['translations' => fn ($query) => $query
            ->whereIn('locale', StorefrontLocaleUrlService::LOCALES)])->find($pageId);
        $urls = collect();
        foreach (StorefrontLocaleUrlService::LOCALES as $locale) {
            $translation = $page?->translations->firstWhere('locale', $locale);
            if ($translation) {
                $urls->put($locale, route('shop.pages.show', [
                    'locale' => $locale,
                    'slug' => $translation->slug,
                ]));
            }
        }

        return $this->withDefault($urls);
    }

    /** @return Collection<int, string> */
    public function sitemapUrls(?CarbonInterface $at = null): Collection
    {
        $at ??= now();
        $categories = Category::query()
            ->with(['translations' => fn ($query) => $query
                ->whereIn('locale', StorefrontLocaleUrlService::LOCALES)])
            ->orderBy('id')
            ->get()
            ->keyBy('id');
        $urls = collect();

        foreach (StorefrontLocaleUrlService::LOCALES as $locale) {
            $urls->push(route('shop.home', ['locale' => $locale]));
            $urls->push(route('shop.products.index', ['locale' => $locale]));

            foreach ($this->reachableCategories($categories, $locale) as $category) {
                $translation = $category->translations->firstWhere('locale', $locale);
                $urls->push(route('shop.categories.show', [
                    'locale' => $locale,
                    'slug' => $translation->slug,
                ]));
            }

            foreach ($this->products->eligibleSitemapProducts($locale, $at) as $product) {
                $urls->push(route('shop.products.show', [
                    'locale' => $locale,
                    'url_key' => $product->url_key,
                ]));
            }

            foreach ($this->content->pages($locale)->sortBy('id') as $page) {
                $urls->push(route('shop.pages.show', [
                    'locale' => $locale,
                    'slug' => $page->translations->first()->slug,
                ]));
            }
        }

        return $urls->map(function (string $url): string {
            $parts = parse_url($url);
            if (! isset($parts['scheme'], $parts['host']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw new LogicException("Invalid sitemap URL [{$url}].");
            }

            return $url;
        })->unique()->values();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function reachableCategories(Collection $categories, string $locale): Collection
    {
        $resolved = [];
        $reachable = function (Category $category) use (&$reachable, &$resolved, $categories, $locale): bool {
            if (array_key_exists($category->id, $resolved)) {
                return $resolved[$category->id];
            }
            if (! $category->status || $category->translations->firstWhere('locale', $locale) === null) {
                return $resolved[$category->id] = false;
            }
            if ($category->parent_id === null) {
                return $resolved[$category->id] = true;
            }
            $parent = $categories->get($category->parent_id);

            return $resolved[$category->id] = $parent !== null && $reachable($parent);
        };

        return $categories
            ->filter(fn (Category $category): bool => $reachable($category))
            ->sortBy('id')
            ->values();
    }

    /**
     * @param  Collection<string, string>  $urls
     * @return array<int, array{hreflang: string, href: string}>
     */
    private function withDefault(Collection $urls): array
    {
        $links = $urls->map(fn (string $href, string $locale): array => [
            'hreflang' => $locale,
            'href' => $href,
        ])->values();
        $defaultHref = $urls->get((string) setting('localization.default_locale')) ?? $urls->first();
        if ($defaultHref) {
            $links->push(['hreflang' => 'x-default', 'href' => $defaultHref]);
        }

        return $links->all();
    }
}
