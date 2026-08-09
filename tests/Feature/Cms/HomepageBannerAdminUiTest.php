<?php

namespace Tests\Feature\Cms;

use App\Models\HomepageBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomepageBannerAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders_the_shared_form_inside_the_admin_layout(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.homepage-banners.create'));

        $response->assertOk()
            ->assertSee('data-layout="vertical"', false)
            ->assertSee('class="body-wrapper-inner"', false)
            ->assertSee('Content Information')
            ->assertSee('English Content')
            ->assertSee('Arabic Content')
            ->assertSee('Image')
            ->assertSee('Save')
            ->assertSee('onsubmit="disableSubmitButton(this)"', false)
            ->assertSee('action="'.route('admin.homepage-banners.store').'"', false)
            ->assertSee('name="title_en"', false)
            ->assertSee('name="title_ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('recommended 1600 × 1600 px (1:1)')
            ->assertSee('recommended 1200 × 1200 px (1:1)')
            ->assertSee('recommended 1200 × 900 px (4:3)')
            ->assertSee('Images are center-cropped to fit')
            ->assertSee('Create Homepage Content')
            ->assertDontSee("@include('admin.homepage-banners._form')", false);
    }

    public function test_edit_page_renders_existing_values_method_and_safe_image_preview(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('homepage/existing.jpg', 'image');
        $banner = HomepageBanner::query()->create([
            'placement' => 'hero',
            'image_path' => 'homepage/existing.jpg',
            'is_active' => true,
            'sort_order' => 4,
        ]);
        $banner->translations()->createMany([
            ['locale' => 'en', 'title' => 'Existing Hero', 'image_alt' => 'Existing image'],
            ['locale' => 'ar', 'title' => 'Arabic Hero'],
        ]);

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.homepage-banners.edit', $banner));

        $response->assertOk()
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('action="'.route('admin.homepage-banners.update', $banner).'"', false)
            ->assertSee('value="Existing Hero"', false)
            ->assertSee('Current Image')
            ->assertSee(asset('storage/homepage/existing.jpg'), false)
            ->assertSee('Edit Homepage Content')
            ->assertSee('Save')
            ->assertDontSee("@include('admin.homepage-banners._form')", false);
    }

    public function test_validation_feedback_is_connected_and_old_values_are_preserved(): void
    {
        $admin = User::factory()->create();
        $createUrl = route('admin.homepage-banners.create');

        $this->actingAs($admin, 'admin')
            ->from($createUrl)
            ->post(route('admin.homepage-banners.store'), [
                'placement' => 'hero',
                'sort_order' => -1,
                'is_active' => 0,
                'title_en' => '',
                'title_ar' => '',
                'eyebrow_en' => 'Preserved eyebrow',
            ])
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['sort_order', 'title_en', 'title_ar']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSee('value="Preserved eyebrow"', false)
            ->assertSee('id="sort_order"', false)
            ->assertSee('is-invalid', false)
            ->assertSee('invalid-feedback', false)
            ->assertSee('for="title_en"', false)
            ->assertSee('id="title_en"', false);
    }
}
