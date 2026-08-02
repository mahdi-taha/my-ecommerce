<?php

namespace App\Services;

use App\Models\HomepageBanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomepageBannerService
{
    public function __construct(private StorefrontLocaleUrlService $urls) {}

    public function create(array $data, ?UploadedFile $image): HomepageBanner
    {
        return $this->persist(null, $data, $image);
    }

    public function update(HomepageBanner $banner, array $data, ?UploadedFile $image): HomepageBanner
    {
        return $this->persist($banner, $data, $image);
    }

    public function delete(HomepageBanner $banner): void
    {
        $path = DB::transaction(function () use ($banner): ?string {
            $locked = HomepageBanner::query()->whereKey($banner->id)->lockForUpdate()->firstOrFail();
            $path = $locked->image_path;
            $locked->delete();

            return $path;
        });
        if ($path) {
            Storage::disk('public')->delete($path);
        }
        $this->forget();
    }

    private function persist(?HomepageBanner $banner, array $data, ?UploadedFile $image): HomepageBanner
    {
        $newPath = $image?->store('homepage', 'public');
        $oldPath = null;
        try {
            $result = DB::transaction(function () use ($banner, $data, $newPath, &$oldPath) {
                $locked = $banner ? HomepageBanner::query()->whereKey($banner->id)->lockForUpdate()->firstOrFail() : new HomepageBanner;
                $oldPath = $locked->image_path;
                $path = $newPath ?? $oldPath;
                if (($data['is_active'] ?? false) && (! $path || ! Storage::disk('public')->exists($path))) {
                    throw ValidationException::withMessages(['image' => 'A valid image is required before activation.']);
                }
                $locked->fill(['placement' => $data['placement'], 'image_path' => $path, 'is_active' => (bool) ($data['is_active'] ?? false), 'sort_order' => $data['sort_order']])->save();
                foreach (['en', 'ar'] as $locale) {
                    $locked->translations()->updateOrCreate(['locale' => $locale], ['title' => trim($data["title_{$locale}"]), 'eyebrow' => $this->nullable($data["eyebrow_{$locale}"] ?? null), 'body' => $this->nullable($data["body_{$locale}"] ?? null), 'button_label' => $this->nullable($data["button_label_{$locale}"] ?? null), 'link_url' => $this->urls->normalizeStoredUrl($this->nullable($data["link_url_{$locale}"] ?? null), $locale), 'image_alt' => $this->nullable($data["image_alt_{$locale}"] ?? null)]);
                }

                return $locked->refresh();
            });
        } catch (Throwable $e) {
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            throw $e;
        }
        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
        $this->forget();

        return $result;
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function forget(): void
    {
        foreach (['en', 'ar'] as $locale) {
            Cache::forget("storefront.homepage.{$locale}");
        }
    }
}
