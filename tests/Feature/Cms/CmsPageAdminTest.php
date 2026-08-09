<?php

namespace Tests\Feature\Cms;

use App\Models\CmsPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_listing_formats_updated_time_in_application_timezone_without_changing_sorting(): void
    {
        config(['app.timezone' => 'Asia/Beirut']);
        $this->seed(CmsPageSeeder::class);
        $older = CmsPage::query()->where('code', 'about')->firstOrFail();
        $newer = CmsPage::query()->where('code', 'contact')->firstOrFail();

        DB::table('cms_pages')->where('id', $older->id)->update([
            'updated_at' => CarbonImmutable::create(2026, 1, 2, 10, 15, 0, 'UTC'),
        ]);
        DB::table('cms_pages')->where('id', $newer->id)->update([
            'updated_at' => CarbonImmutable::create(2026, 1, 2, 11, 30, 0, 'UTC'),
        ]);

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->getJson(route('admin.cms-pages.index', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'columns' => [
                    ['data' => 'title', 'name' => 'title', 'searchable' => 'false', 'orderable' => 'false'],
                    ['data' => 'code', 'name' => 'code', 'searchable' => 'true', 'orderable' => 'true'],
                    ['data' => 'is_active', 'name' => 'is_active', 'searchable' => 'false', 'orderable' => 'true'],
                    ['data' => 'sort_order', 'name' => 'sort_order', 'searchable' => 'true', 'orderable' => 'true'],
                    ['data' => 'updated_at', 'name' => 'updated_at', 'searchable' => 'true', 'orderable' => 'true'],
                    ['data' => 'action', 'name' => 'action', 'searchable' => 'false', 'orderable' => 'false'],
                ],
                'order' => [['column' => 4, 'dir' => 'asc']],
                'search' => ['value' => '', 'regex' => 'false'],
            ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $this->assertSame($older->id, $response->json('data.0.id'));
        $this->assertSame('2026-01-02 12:15', $response->json('data.0.updated_at'));
        $this->assertSame($newer->id, $response->json('data.1.id'));
        $this->assertSame('2026-01-02 13:30', $response->json('data.1.updated_at'));
    }
}
