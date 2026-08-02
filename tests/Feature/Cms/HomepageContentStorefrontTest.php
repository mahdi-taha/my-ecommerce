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
}
