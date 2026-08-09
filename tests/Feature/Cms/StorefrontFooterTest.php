<?php

namespace Tests\Feature\Cms;

use App\Models\CmsPage;
use App\Models\Setting;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontFooterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(SettingSeeder::class);
    }

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

    public function test_footer_renders_only_valid_configured_social_links(): void
    {
        $this->setSetting('facebook_url', ' https://facebook.com/configured-store ');
        $this->setSetting('instagram_url', 'https://instagram.com/configured-store');
        $this->setSetting('whatsapp_url', 'https://wa.me/96170000000');

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('storefront-footer-contact', false)
            ->assertSee('storefront-footer-navigation', false)
            ->assertSee('storefront-footer-social-column', false)
            ->assertSee('storefront-footer-social-links', false)
            ->assertSee(__('shop.cms.follow_us'))
            ->assertSee('href="https://facebook.com/configured-store"', false)
            ->assertSee('href="https://instagram.com/configured-store"', false)
            ->assertSee('href="https://wa.me/96170000000"', false)
            ->assertSee('bi bi-facebook', false)
            ->assertSee('bi bi-instagram', false)
            ->assertSee('bi bi-whatsapp', false)
            ->assertSee('aria-label="'.__('shop.topbar.facebook').'"', false)
            ->assertSee('aria-label="'.__('shop.topbar.instagram').'"', false)
            ->assertSee('aria-label="'.__('shop.topbar.whatsapp').'"', false);

        $footer = $this->footerFrom($response->getContent());
        $this->assertLessThan(
            strpos($footer, 'storefront-footer-navigation'),
            strpos($footer, 'storefront-footer-contact')
        );
        $this->assertLessThan(
            strpos($footer, 'storefront-footer-social-column'),
            strpos($footer, 'storefront-footer-navigation')
        );
        $this->assertStringNotContainsString(
            'storefront-footer-social-links',
            $this->footerContactFrom($footer)
        );
        $this->assertSame(3, substr_count($footer, 'target="_blank"'));
        $this->assertSame(3, substr_count($footer, 'rel="noopener noreferrer"'));
    }

    public function test_footer_omits_empty_and_invalid_legacy_social_links(): void
    {
        $this->setSetting('facebook_url', 'javascript:alert(1)');
        $this->setSetting('instagram_url', 'ftp://example.com/store');
        $this->setSetting('whatsapp_url', '');

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertDontSee('storefront-footer-social-links', false)
            ->assertDontSee(__('shop.cms.follow_us'))
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('ftp://example.com/store', false);
    }

    public function test_footer_social_links_use_localized_accessible_labels_in_rtl(): void
    {
        $this->setSetting('facebook_url', 'https://facebook.com/configured-store');

        $this->get(route('shop.home', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.cms.follow_us', [], 'ar'))
            ->assertSee('aria-label="'.__('shop.topbar.facebook', [], 'ar').'"', false);
    }

    private function setSetting(string $key, ?string $value): void
    {
        Setting::query()->where('group', 'store')->where('key', $key)->update(['value' => $value]);
        Cache::forget("setting.store.{$key}");
    }

    private function footerFrom(string $html): string
    {
        preg_match('/<footer\b.*?<\/footer>/s', $html, $matches);
        $this->assertArrayHasKey(0, $matches);

        return $matches[0];
    }

    private function footerContactFrom(string $footer): string
    {
        preg_match('/<div class="col-md-4 storefront-footer-contact">(.*?)<\/div>/s', $footer, $matches);
        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }
}
