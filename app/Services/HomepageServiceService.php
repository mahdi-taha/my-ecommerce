<?php

namespace App\Services;

use App\Models\HomepageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomepageServiceService
{
    public const MAX_ACTIVE = 6;

    public function create(array $data): HomepageService
    {
        return $this->persist(null, $data);
    }

    public function update(HomepageService $service, array $data): HomepageService
    {
        return $this->persist($service, $data);
    }

    public function delete(HomepageService $service): void
    {
        DB::transaction(function () use ($service): void {
            $this->lockActivationLimit();
            HomepageService::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail()->delete();
            $this->forgetAfterCommit();
        });
    }

    private function persist(?HomepageService $service, array $data): HomepageService
    {
        return DB::transaction(function () use ($service, $data): HomepageService {
            $this->lockActivationLimit();
            $locked = $service
                ? HomepageService::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail()
                : new HomepageService;

            if ((bool) ($data['is_active'] ?? false)) {
                $activeCount = HomepageService::query()
                    ->active()
                    ->when($locked->exists, fn ($query) => $query->whereKeyNot($locked->getKey()))
                    ->count();

                if ($activeCount >= self::MAX_ACTIVE) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Only six homepage services may be active at one time.',
                    ]);
                }
            }

            $locked->fill([
                'icon' => $data['icon'],
                'is_active' => (bool) ($data['is_active'] ?? false),
                'sort_order' => $data['sort_order'],
            ])->save();

            foreach (['en', 'ar'] as $locale) {
                $locked->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'title' => trim($data["title_{$locale}"]),
                        'description' => trim($data["description_{$locale}"]),
                    ]
                );
            }

            $this->forgetAfterCommit();

            return $locked->load('translations');
        });
    }

    private function lockActivationLimit(): void
    {
        DB::table('homepage_service_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    private function forgetAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            foreach (['en', 'ar'] as $locale) {
                Cache::forget("storefront.homepage.services.{$locale}");
            }
        });
    }
}
