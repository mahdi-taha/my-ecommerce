<?php

namespace Tests\Feature\Cms;

use App\Models\CmsPage;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CmsPageStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_active_current_locale_page_renders_escaped_body_and_seo_without_fallback(): void
    {
        $this->seed(CmsPageSeeder::class);
        $page = CmsPage::where('code', 'about')->firstOrFail();
        $page->update(['is_active' => true]);
        $page->translations()->where('locale', 'en')->update(['body' => "Line one\n<script>alert(1)</script>", 'meta_title' => 'About Meta', 'meta_description' => 'Description']);
        Cache::flush();
        $this->get(route('shop.pages.show', 'about-us'))->assertOk()->assertSee('About Meta')->assertSee('Line one<br />', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->withSession(['storefront_locale' => 'ar'])->get(route('shop.pages.show', 'about-us'))->assertNotFound();
        $page->translations()->where('locale', 'en')->update(['meta_description' => '   ']);
        Cache::flush();
        $this->withSession(['storefront_locale' => 'en'])->get(route('shop.pages.show', 'about-us'))->assertOk()->assertDontSee('<meta name="description"', false);
        $page->update(['is_active' => false]);
        Cache::flush();
        $this->get(route('shop.pages.show', 'about-us'))->assertNotFound();
    }
}
