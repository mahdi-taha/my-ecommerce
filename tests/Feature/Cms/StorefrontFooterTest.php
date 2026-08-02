<?php

namespace Tests\Feature\Cms;

use App\Models\CmsPage;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_uses_active_cms_pages_and_contact_nav_hides_when_unpublished(): void
    {
        $this->seed(CmsPageSeeder::class);
        Cache::flush();
        $this->get(route('shop.home'))->assertDontSee(route('shop.pages.show', 'contact-us'));
        $page = CmsPage::where('code', 'contact')->firstOrFail();
        $page->update(['is_active' => true]);
        $page->translations()->where('locale', 'en')->update(['body' => 'Contact body']);
        Cache::flush();
        $this->get(route('shop.home'))->assertSee(route('shop.pages.show', 'contact-us'))->assertSee('Contact Us');
    }
}
