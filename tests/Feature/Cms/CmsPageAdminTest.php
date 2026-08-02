<?php

namespace Tests\Feature\Cms;

use App\Models\CmsPage;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CmsPageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_publish_bilingual_fixed_page_but_cannot_create_or_delete(): void
    {
        $this->seed(CmsPageSeeder::class);
        $page = CmsPage::where('code', 'about')->firstOrFail();
        $payload = ['is_active' => 1, 'sort_order' => 2, 'title_en' => 'About', 'slug_en' => 'about', 'body_en' => 'English body', 'meta_title_en' => null, 'meta_description_en' => null, 'title_ar' => 'من نحن', 'slug_ar' => 'من-نحن', 'body_ar' => 'النص العربي', 'meta_title_ar' => null, 'meta_description_ar' => null];
        $this->actingAs(User::factory()->create(), 'admin')->put(route('admin.cms-pages.update', $page), $payload)->assertRedirect();
        $this->assertTrue($page->fresh()->is_active);
        $this->assertFalse(Route::has('admin.cms-pages.store'));
        $this->assertFalse(Route::has('admin.cms-pages.destroy'));
    }
}
