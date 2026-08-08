<?php

namespace App\Services;

use App\Models\Category;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use LogicException;

class StorefrontSeoService
{
    public function __construct(
        private StorefrontProductListingService $products,
        private StorefrontContentService $content,
    ) {}

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
}
