<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\CmsPage;
use App\Models\CmsPageTranslation;
use App\Models\ProductTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

class StorefrontLocaleUrlService
{
    public const LOCALES = ['en', 'ar'];

    private const PUBLIC_ROUTES = [
        'shop.home',
        'shop.products.index',
        'shop.products.show',
        'shop.categories.show',
        'shop.pages.show',
        'shop.cart.index',
        'shop.checkout.show',
        'customer.login',
        'customer.register',
        'customer.password.request',
        'customer.password.reset',
    ];

    public function supported(string $locale): bool
    {
        return in_array($locale, self::LOCALES, true);
    }

    public function defaultLocale(): string
    {
        $locale = (string) setting('localization.default_locale', config('app.locale'));

        return $this->supported($locale) ? $locale : 'en';
    }

    public function equivalent(Request $request, string $targetLocale): string
    {
        $route = $request->route();
        $name = $route?->getName();
        $parameters = $route?->parameters() ?? [];
        $query = $request->query();
        $sourceLocale = (string) ($parameters['locale'] ?? app()->getLocale());

        if (! $this->supported($targetLocale) || ! is_string($name)) {
            return route('shop.home', ['locale' => $this->defaultLocale()]);
        }

        unset($parameters['locale']);

        if ($name === 'shop.products.show') {
            return $this->productEquivalent((string) ($parameters['url_key'] ?? ''), $sourceLocale, $targetLocale, $query);
        }

        if ($name === 'shop.categories.show') {
            return $this->categoryEquivalent((string) ($parameters['slug'] ?? ''), $sourceLocale, $targetLocale, $query);
        }

        if ($name === 'shop.pages.show') {
            return $this->cmsEquivalent((string) ($parameters['slug'] ?? ''), $sourceLocale, $targetLocale, $query);
        }

        if ($name === 'customer.password.reset') {
            return route($name, ['locale' => $targetLocale, ...$parameters, ...$query]);
        }

        if (in_array($name, self::PUBLIC_ROUTES, true)) {
            return route($name, ['locale' => $targetLocale, ...$parameters, ...$query]);
        }

        return route('shop.home', ['locale' => $targetLocale]);
    }

    public function capture(Request $request): void
    {
        $route = $request->route();
        $name = $route?->getName();
        if (! is_string($name) || $name === 'shop.locale.update' || str_starts_with($name, 'admin.')) {
            return;
        }

        $parameters = $route->parameters();
        unset($parameters['locale']);
        $context = [
            'name' => $name,
            'parameters' => $parameters,
            'query' => $request->query(),
            'source_locale' => app()->getLocale(),
        ];

        if ($name === 'shop.products.show') {
            $context['entity_id'] = ProductTranslation::query()
                ->where('locale', app()->getLocale())
                ->where('url_key', (string) ($parameters['url_key'] ?? ''))
                ->value('product_id');
        } elseif ($name === 'shop.categories.show') {
            $context['entity_id'] = CategoryTranslation::query()
                ->where('locale', app()->getLocale())
                ->where('slug', (string) ($parameters['slug'] ?? ''))
                ->value('category_id');
        } elseif ($name === 'shop.pages.show') {
            $context['entity_id'] = CmsPageTranslation::query()
                ->where('locale', app()->getLocale())
                ->where('slug', (string) ($parameters['slug'] ?? ''))
                ->value('cms_page_id');
        }

        $request->session()->put('storefront_locale_context', $context);
    }

    public function equivalentFromSession(Request $request, string $targetLocale): string
    {
        $context = $request->session()->get('storefront_locale_context');
        if (! is_array($context) || ! $this->supported($targetLocale)) {
            return route('shop.home', ['locale' => $targetLocale]);
        }

        $name = $context['name'] ?? null;
        $parameters = is_array($context['parameters'] ?? null) ? $context['parameters'] : [];
        $query = is_array($context['query'] ?? null) ? $context['query'] : [];
        $entityId = $context['entity_id'] ?? null;

        if ($name === 'shop.products.show' && $entityId) {
            $target = ProductTranslation::query()->where('product_id', $entityId)->where('locale', $targetLocale)->first();

            return $target
                ? route($name, ['locale' => $targetLocale, 'url_key' => $target->url_key, ...$query])
                : route('shop.home', ['locale' => $targetLocale]);
        }

        if ($name === 'shop.categories.show' && $entityId) {
            $category = Category::query()->find($entityId);
            if (! $category || ! $this->reachableCategory($category, $targetLocale)) {
                return route('shop.home', ['locale' => $targetLocale]);
            }
            $target = $category->translations()->where('locale', $targetLocale)->first();

            return route($name, ['locale' => $targetLocale, 'slug' => $target->slug, ...$query]);
        }

        if ($name === 'shop.pages.show' && $entityId) {
            $page = CmsPage::query()->active()->find($entityId);
            $target = $page?->translations()->where('locale', $targetLocale)->first();

            return $target
                ? route($name, ['locale' => $targetLocale, 'slug' => $target->slug, ...$query])
                : route('shop.home', ['locale' => $targetLocale]);
        }

        if ($name === 'customer.password.reset') {
            return route($name, ['locale' => $targetLocale, ...$parameters, ...$query]);
        }

        if (is_string($name) && Route::has($name) && ! str_starts_with($name, 'admin.')) {
            return route($name, ['locale' => $targetLocale, ...$parameters, ...$query]);
        }

        return route('shop.home', ['locale' => $targetLocale]);
    }

    public function normalizeStoredUrl(?string $url, string $locale): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with(strtolower($url), 'https://')) {
            return $url === '' ? null : $url;
        }

        if (! $this->supported($locale) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        $matched = $this->match($url);
        if ($matched === null || $matched->getName() === null) {
            $parts = parse_url($url);
            $path = '/'.$this->defaultLocale().'/'.ltrim((string) ($parts['path'] ?? '/'), '/');
            $url = rtrim($path, '/').(filled($parts['query'] ?? null) ? '?'.$parts['query'] : '');
            $matched = $this->match($url);
        }
        if ($matched === null || ! in_array($matched->getName(), self::PUBLIC_ROUTES, true)) {
            return null;
        }

        $parameters = $matched->parameters();
        $hasLocale = isset($parameters['locale']);
        if ($hasLocale && ! $this->supported((string) $parameters['locale'])) {
            return null;
        }

        $sourceLocale = $hasLocale ? (string) $parameters['locale'] : $this->defaultLocale();
        $request = Request::create($url, 'GET');
        $request->setRouteResolver(fn () => $matched);

        return $this->equivalent($request, $locale);
    }

    public function legacyEntity(string $type, string $key): ?string
    {
        $locale = $this->defaultLocale();

        return match ($type) {
            'product' => $this->productEquivalent($key, $locale, $locale, []),
            'category' => $this->categoryEquivalent($key, $locale, $locale, []),
            'page' => $this->cmsEquivalent($key, $locale, $locale, []),
            default => null,
        };
    }

    private function productEquivalent(string $key, string $sourceLocale, string $targetLocale, array $query): string
    {
        $translation = ProductTranslation::query()
            ->where('locale', $sourceLocale)
            ->where('url_key', $key)
            ->first();
        $product = $translation?->product()->active()->visible()
            ->whereNull('configurable_id')
            ->whereIn('type', [ProductType::Simple->value, ProductType::Configurable->value])
            ->withStorefrontCardData($sourceLocale)
            ->first();
        $eligible = $product !== null && match ($product->type) {
            ProductType::Simple->value => $product->hasPositiveEffectivePrice(),
            ProductType::Configurable->value => $product->eligibleStorefrontVariants()->isNotEmpty(),
            default => false,
        };
        $target = $eligible
            ? $product->translations()->where('locale', $targetLocale)->first()
            : null;

        return $target
            ? route('shop.products.show', ['locale' => $targetLocale, 'url_key' => $target->url_key, ...$query])
            : route('shop.home', ['locale' => $targetLocale]);
    }

    private function categoryEquivalent(string $slug, string $sourceLocale, string $targetLocale, array $query): string
    {
        $translation = CategoryTranslation::query()
            ->where('locale', $sourceLocale)
            ->where('slug', $slug)
            ->first();
        $category = $translation?->category()->where('status', true)->first();
        if (! $category || ! $this->reachableCategory($category, $targetLocale)) {
            return route('shop.home', ['locale' => $targetLocale]);
        }

        $target = $category->translations()->where('locale', $targetLocale)->first();

        return route('shop.categories.show', ['locale' => $targetLocale, 'slug' => $target->slug, ...$query]);
    }

    private function cmsEquivalent(string $slug, string $sourceLocale, string $targetLocale, array $query): string
    {
        $translation = CmsPageTranslation::query()
            ->where('locale', $sourceLocale)
            ->where('slug', $slug)
            ->first();
        $page = $translation?->page()->active()->first();
        $target = $page?->translations()->where('locale', $targetLocale)->first();

        return $target
            ? route('shop.pages.show', ['locale' => $targetLocale, 'slug' => $target->slug, ...$query])
            : route('shop.home', ['locale' => $targetLocale]);
    }

    private function reachableCategory(Category $category, string $locale): bool
    {
        while ($category !== null) {
            if (! $category->status || ! $category->translations()->where('locale', $locale)->exists()) {
                return false;
            }

            $category = $category->parent;
        }

        return true;
    }

    private function match(string $url): mixed
    {
        try {
            return Route::getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return null;
        }
    }
}
