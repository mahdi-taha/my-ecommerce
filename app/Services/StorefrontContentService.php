<?php

namespace App\Services;

use App\Models\CmsPage;
use App\Models\HomepageBanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class StorefrontContentService
{
    public function pages(string $locale): Collection
    {
        return Cache::rememberForever("storefront.cms.pages.{$locale}", fn () => CmsPage::query()->active()->whereHas('translations', fn ($q) => $q->where('locale', $locale))->with(['translations' => fn ($q) => $q->where('locale', $locale)])->orderBy('sort_order')->orderBy('id')->get());
    }

    public function pageBySlug(string $slug, string $locale): ?CmsPage
    {
        return $this->pages($locale)->first(fn (CmsPage $page) => $page->translations->first()?->slug === $slug);
    }

    public function pageByCode(string $code, string $locale): ?CmsPage
    {
        return $this->pages($locale)->firstWhere('code', $code);
    }

    public function footerPages(string $locale): Collection
    {
        return Cache::rememberForever("storefront.cms.footer.{$locale}", fn () => $this->pages($locale));
    }

    public function homepage(string $locale): Collection
    {
        return Cache::rememberForever("storefront.homepage.{$locale}", fn () => HomepageBanner::query()->active()->whereHas('translations', fn ($q) => $q->where('locale', $locale))->with(['translations' => fn ($q) => $q->where('locale', $locale)])->orderBy('placement')->orderBy('sort_order')->orderBy('id')->get()->filter(fn ($banner) => filled($banner->image_path) && Storage::disk('public')->exists($banner->image_path))->each(fn ($banner) => $banner->setAttribute('image_url', Storage::disk('public')->url($banner->image_path)))->values());
    }

    public function external(?string $url): bool
    {
        return filled($url) && str_starts_with(strtolower($url), 'https://');
    }
}
