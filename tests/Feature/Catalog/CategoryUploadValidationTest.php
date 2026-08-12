<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_category_images_are_accepted_for_create_and_edit(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.categories.store'), $this->categoryData([
                'logo' => UploadedFile::fake()->image('logo.jpg')->size(5120),
                'banner' => UploadedFile::fake()->image('banner.png')->size(5120),
            ]))
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->firstOrFail();
        Storage::disk('public')->assertExists($category->logo_path);
        Storage::disk('public')->assertExists($category->banner_path);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.categories.update', $category), $this->categoryData([
                'category_slug_en' => 'updated-category',
                'category_slug_ar' => 'updated-category-ar',
                'logo' => UploadedFile::fake()->image('updated.webp'),
            ]))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSame('updated-category', $category->fresh()->translations()->where('locale', 'en')->value('slug'));
    }

    public function test_special_original_filenames_are_replaced_with_safe_server_generated_names(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create(), 'admin')
            ->post(route('admin.categories.store'), $this->categoryData([
                'logo' => UploadedFile::fake()->image("Sharaf Electro Store_ Kıbrıs'ın Elektronik Marketi.JPG"),
                'banner' => UploadedFile::fake()->image('صورة الفئة الرئيسية.PNG'),
            ]))
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->firstOrFail();

        $this->assertMatchesRegularExpression('#^categories/logos/[A-Za-z0-9]{40}\.jpg$#', $category->logo_path);
        $this->assertMatchesRegularExpression('#^categories/banners/[A-Za-z0-9]{40}\.png$#', $category->banner_path);
        $this->assertStringNotContainsString('Sharaf', $category->logo_path);
        $this->assertStringNotContainsString('صورة', $category->banner_path);
        Storage::disk('public')->assertExists($category->logo_path);
        Storage::disk('public')->assertExists($category->banner_path);
    }

    public function test_replacing_category_images_deletes_previous_files_and_keeps_safe_paths(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.categories.store'), $this->categoryData([
                'logo' => UploadedFile::fake()->image('old logo.jpg'),
                'banner' => UploadedFile::fake()->image('old banner.png'),
            ]))
            ->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->firstOrFail();
        $oldLogoPath = $category->logo_path;
        $oldBannerPath = $category->banner_path;

        $this->actingAs($admin, 'admin')
            ->put(route('admin.categories.update', $category), $this->categoryData([
                'category_slug_en' => 'replacement-category',
                'category_slug_ar' => 'replacement-category-ar',
                'logo' => UploadedFile::fake()->image("new logo's çığ.JPG"),
                'banner' => UploadedFile::fake()->image('لافتة جديدة.WEBP'),
            ]))
            ->assertRedirect(route('admin.categories.index'));

        $category->refresh();

        Storage::disk('public')->assertMissing($oldLogoPath);
        Storage::disk('public')->assertMissing($oldBannerPath);
        Storage::disk('public')->assertExists($category->logo_path);
        Storage::disk('public')->assertExists($category->banner_path);
        $this->assertMatchesRegularExpression('#^categories/logos/[A-Za-z0-9]{40}\.jpg$#', $category->logo_path);
        $this->assertMatchesRegularExpression('#^categories/banners/[A-Za-z0-9]{40}\.webp$#', $category->banner_path);
    }

    public function test_oversized_category_images_are_rejected(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->post(route('admin.categories.store'), $this->categoryData([
                'logo' => UploadedFile::fake()->image('logo.jpg')->size(5121),
                'banner' => UploadedFile::fake()->image('banner.png')->size(5121),
            ]));

        $response->assertSessionHasErrors(['logo', 'banner']);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_invalid_category_image_mime_types_are_rejected(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->post(route('admin.categories.store'), $this->categoryData([
                'logo' => UploadedFile::fake()->create('logo.gif', 10, 'image/gif'),
                'banner' => UploadedFile::fake()->create('banner.pdf', 10, 'application/pdf'),
            ]));

        $response->assertSessionHasErrors(['logo', 'banner']);
        $this->assertDatabaseCount('categories', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function categoryData(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => null,
            'position' => 0,
            'status' => '1',
            'category_name_en' => 'Category',
            'category_slug_en' => 'category-'.uniqid(),
            'category_name_ar' => 'Category Arabic',
            'category_slug_ar' => 'category-ar-'.uniqid(),
            'filterable_attributes' => [],
        ], $overrides);
    }
}
