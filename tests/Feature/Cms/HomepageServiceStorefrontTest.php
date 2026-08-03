<?php

namespace Tests\Feature\Cms;

use App\Enums\HomepageServiceIcon;
use App\Models\HomepageService;
use App\Services\HomepageServiceService;
use App\Services\StorefrontContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HomepageServiceStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Cache::flush();
    }

    public function test_homepage_renders_only_active_complete_current_locale_services_in_order(): void
    {
        $second = $this->service('Second', 'الثاني', 2);
        $first = $this->service('First', 'الأول', 1);
        $this->service('Inactive', 'غير نشط', 0, false);
        $incomplete = HomepageService::query()->create([
            'icon' => HomepageServiceIcon::Support,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $incomplete->translations()->create(['locale' => 'en', 'title' => 'Incomplete', 'description' => '']);

        $english = $this->get('/en')->assertOk()
            ->assertSee('services-count-2', false)
            ->assertSee($first->icon->cssClass(), false)
            ->assertSee('aria-hidden="true"', false)
            ->assertDontSee('Inactive')
            ->assertDontSee('Incomplete');
        $this->assertLessThan(strpos($english->getContent(), 'Second'), strpos($english->getContent(), 'First'));

        $this->get('/ar')->assertOk()
            ->assertSee('الأول')
            ->assertSee('الثاني')
            ->assertDontSee('First');
    }

    public function test_section_is_hidden_when_empty_and_no_static_fallback_remains(): void
    {
        $this->get('/en')->assertOk()
            ->assertDontSee('storefront-services', false)
            ->assertDontSee('Free Return')
            ->assertDontSee('Support 24/7');
    }

    public function test_resolution_is_cached_and_query_count_does_not_grow_with_service_count(): void
    {
        foreach (range(1, 8) as $position) {
            $this->service("Service {$position}", "خدمة {$position}", $position);
        }

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $content = app(StorefrontContentService::class);
        $services = $content->homepageServices('en');
        $firstQueryCount = count(DB::getQueryLog());
        $again = $content->homepageServices('en');

        $this->assertCount(6, $services);
        $this->assertSame($services, $again);
        $this->assertSame(2, $firstQueryCount);
        $this->assertSame($firstQueryCount, count(DB::getQueryLog()));

        $css = file_get_contents(resource_path('css/shop.css'));
        $this->assertStringContainsString('.services-count-5 > :nth-child(4)', $css);
        $this->assertStringContainsString('.services-count-5 > :nth-child(5)', $css);
    }

    public function test_committed_changes_invalidate_both_locale_caches_and_failed_activation_preserves_them(): void
    {
        try {
            DB::commit();
            $manager = app(HomepageServiceService::class);
            Cache::put('storefront.homepage.services.en', collect(['stale']));
            Cache::put('storefront.homepage.services.ar', collect(['stale']));

            $created = $manager->create($this->payload('Created', true));
            $this->assertFalse(Cache::has('storefront.homepage.services.en'));
            $this->assertFalse(Cache::has('storefront.homepage.services.ar'));

            foreach (range(2, 6) as $position) {
                $manager->create($this->payload("Service {$position}", true, $position));
            }
            Cache::put('storefront.homepage.services.en', collect(['current']));
            Cache::put('storefront.homepage.services.ar', collect(['current']));

            try {
                $manager->create($this->payload('Seventh', true, 7));
                $this->fail('The seventh active service should be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(Cache::has('storefront.homepage.services.en'));
                $this->assertTrue(Cache::has('storefront.homepage.services.ar'));
            }

            $manager->delete($created);
            $this->assertFalse(Cache::has('storefront.homepage.services.en'));
            $this->assertFalse(Cache::has('storefront.homepage.services.ar'));
        } finally {
            HomepageService::query()->delete();
            Cache::flush();
            DB::beginTransaction();
        }
    }

    private function service(string $english, string $arabic, int $order, bool $active = true): HomepageService
    {
        $service = HomepageService::query()->create([
            'icon' => HomepageServiceIcon::Quality,
            'is_active' => $active,
            'sort_order' => $order,
        ]);
        $service->translations()->createMany([
            ['locale' => 'en', 'title' => $english, 'description' => "{$english} description"],
            ['locale' => 'ar', 'title' => $arabic, 'description' => "وصف {$arabic}"],
        ]);

        return $service;
    }

    private function payload(string $title, bool $active, int $order = 1): array
    {
        return [
            'icon' => HomepageServiceIcon::Support->value,
            'is_active' => $active,
            'sort_order' => $order,
            'title_en' => $title,
            'description_en' => "{$title} description",
            'title_ar' => "{$title} ar",
            'description_ar' => "{$title} ar description",
        ];
    }
}
