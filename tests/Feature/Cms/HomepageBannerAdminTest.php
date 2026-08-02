<?php

namespace Tests\Feature\Cms;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageBannerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_banner_requires_valid_image_and_safe_links(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $payload = $this->payload();
        $this->actingAs($admin, 'admin')->post(route('admin.homepage-banners.store'), $payload)->assertSessionHasErrors('image');
        $payload['image'] = UploadedFile::fake()->image('hero.jpg');
        $this->actingAs($admin, 'admin')->post(route('admin.homepage-banners.store'), $payload)->assertRedirect(route('admin.homepage-banners.index'));
        $this->assertDatabaseHas('homepage_banners', ['is_active' => true, 'placement' => 'hero']);
        $payload = $this->payload();
        $payload['link_url_en'] = 'javascript:alert(1)';
        $this->actingAs($admin, 'admin')->post(route('admin.homepage-banners.store'), $payload)->assertSessionHasErrors('link_url_en');
    }

    private function payload(): array
    {
        return ['placement' => 'hero', 'is_active' => 1, 'sort_order' => 0, 'title_en' => 'Hero', 'title_ar' => 'واجهة', 'eyebrow_en' => null, 'eyebrow_ar' => null, 'body_en' => null, 'body_ar' => null, 'button_label_en' => 'Shop', 'button_label_ar' => 'تسوق', 'link_url_en' => '/shop?sort=newest', 'link_url_ar' => '/shop?sort=newest', 'image_alt_en' => 'Hero', 'image_alt_ar' => 'واجهة'];
    }
}
