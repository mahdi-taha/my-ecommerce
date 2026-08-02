<?php

namespace App\Services;

use App\Models\CmsPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CmsPageService
{
    public function update(CmsPage $page, array $data): CmsPage
    {
        $page = DB::transaction(function () use ($page, $data): CmsPage {
            $locked = CmsPage::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
            if (($data['is_active'] ?? false) && (blank($data['body_en'] ?? null) || blank($data['body_ar'] ?? null))) {
                throw ValidationException::withMessages(['body_en' => 'English and Arabic body content is required before publishing.']);
            }
            $locked->update(['is_active' => (bool) ($data['is_active'] ?? false), 'sort_order' => $data['sort_order']]);
            foreach (['en', 'ar'] as $locale) {
                $locked->translations()->updateOrCreate(['locale' => $locale], [
                    'title' => trim($data["title_{$locale}"]), 'slug' => trim($data["slug_{$locale}"]),
                    'body' => $this->nullable($data["body_{$locale}"] ?? null),
                    'meta_title' => $this->nullable($data["meta_title_{$locale}"] ?? null),
                    'meta_description' => $this->nullable($data["meta_description_{$locale}"] ?? null),
                ]);
            }

            return $locked->refresh();
        });

        $this->forgetCaches();

        return $page;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function forgetCaches(): void
    {
        foreach (['en', 'ar'] as $locale) {
            Cache::forget("storefront.cms.pages.{$locale}");
            Cache::forget("storefront.cms.footer.{$locale}");
        }
    }
}
