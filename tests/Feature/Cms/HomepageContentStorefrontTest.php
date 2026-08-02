<?php

namespace Tests\Feature\Cms;

use App\Models\HomepageBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageContentStorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_translated_entries_with_existing_images_render(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('homepage/hero.jpg', 'image');
        $active = HomepageBanner::create(['placement' => 'hero', 'image_path' => 'homepage/hero.jpg', 'is_active' => true, 'sort_order' => 1]);
        $active->translations()->create(['locale' => 'en', 'title' => 'Managed Hero', 'button_label' => 'Shop', 'link_url' => 'https://example.com', 'image_alt' => 'Hero']);
        $missing = HomepageBanner::create(['placement' => 'offer', 'image_path' => 'homepage/missing.jpg', 'is_active' => true]);
        $missing->translations()->create(['locale' => 'en', 'title' => 'Missing Offer']);
        Cache::flush();
        $this->get(route('shop.home'))->assertOk()->assertSee('Managed Hero')->assertSee('noopener noreferrer', false)->assertDontSee('Missing Offer');
    }

    public function test_legacy_local_links_are_rendered_with_the_current_locale_prefix(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('homepage/hero.jpg', 'image');
        $banner = HomepageBanner::create([
            'placement' => 'hero',
            'image_path' => 'homepage/hero.jpg',
            'is_active' => true,
        ]);
        $banner->translations()->create([
            'locale' => 'ar',
            'title' => 'واجهة',
            'button_label' => 'تسوق',
            'link_url' => '/shop?sort=newest',
            'image_alt' => 'واجهة',
        ]);
        Cache::flush();

        $this->get('/ar')
            ->assertOk()
            ->assertSee(url('/ar/shop?sort=newest'), false)
            ->assertDontSee('href="/shop?sort=newest"', false);
    }

    public function test_managed_homepage_images_use_bounded_media_hooks_and_preserve_alt_text(): void
    {
        Storage::fake('public');

        foreach (['hero', 'hero_side', 'offer'] as $placement) {
            $path = "homepage/{$placement}.jpg";
            Storage::disk('public')->put($path, 'image');
            $banner = HomepageBanner::create([
                'placement' => $placement,
                'image_path' => $path,
                'is_active' => true,
            ]);
            $banner->translations()->create([
                'locale' => 'en',
                'title' => str($placement)->replace('_', ' ')->title(),
                'image_alt' => "{$placement} accessible image",
            ]);
        }

        Cache::flush();
        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('storefront-hero-carousel', false)
            ->assertSee('storefront-hero-slide', false)
            ->assertSee('storefront-hero-media', false)
            ->assertSee('storefront-hero-side--paired', false)
            ->assertSee('storefront-offer-card', false)
            ->assertSee('storefront-offer-media', false)
            ->assertSee('alt="hero accessible image"', false)
            ->assertSee('alt="hero_side accessible image"', false)
            ->assertSee('alt="offer accessible image"', false)
            ->assertDontSee('style="object-fit', false);

        $css = file_get_contents(resource_path('css/shop.css'));
        $this->assertStringContainsString('.storefront-hero-carousel', $css);
        $this->assertStringContainsString('min-height: 1px', $css);
        $this->assertStringContainsString('aspect-ratio: 16 / 7', $css);
        $this->assertStringContainsString('aspect-ratio: 16 / 9', $css);
        $this->assertStringContainsString('aspect-ratio: 4 / 3', $css);
        $this->assertStringContainsString('object-fit: cover', $css);
        $this->assertStringContainsString('object-position: center', $css);
    }
}
