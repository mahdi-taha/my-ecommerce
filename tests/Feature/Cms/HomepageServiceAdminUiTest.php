<?php

namespace Tests\Feature\Cms;

use App\Enums\HomepageServiceIcon;
use App\Models\HomepageService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageServiceAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_uses_admin_card_datatable_and_reports_active_count(): void
    {
        $service = $this->service();
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->get(route('admin.homepage-services.index'))
            ->assertOk()
            ->assertSee('id="homepageServicesTable"', false)
            ->assertSee('1 / 6 active')
            ->assertSee(route('admin.homepage-services.create'), false);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.homepage-services.index', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'search' => ['value' => 'Fast', 'regex' => false],
        ]), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.title', 'Fast Shipping');
        $this->assertStringContainsString(route('admin.homepage-services.edit', $service), $response->json('data.0.action'));
    }

    public function test_create_and_edit_share_accessible_bilingual_form_markup(): void
    {
        $service = $this->service();
        $admin = User::factory()->create();

        foreach ([
            route('admin.homepage-services.create'),
            route('admin.homepage-services.edit', $service),
        ] as $url) {
            $this->actingAs($admin, 'admin')->get($url)
                ->assertOk()
                ->assertSee('Service Information')
                ->assertSee('English Content')
                ->assertSee('Arabic Content')
                ->assertSee('for="icon"', false)
                ->assertSee('id="title_ar"', false)
                ->assertSee('dir="rtl"', false);
        }

        $this->assertStringContainsString(
            'invalid-feedback',
            file_get_contents(resource_path('views/admin/homepage-services/_form.blade.php'))
        );
    }

    private function service(): HomepageService
    {
        $service = HomepageService::query()->create([
            'icon' => HomepageServiceIcon::Shipping,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $service->translations()->createMany([
            ['locale' => 'en', 'title' => 'Fast Shipping', 'description' => 'Description'],
            ['locale' => 'ar', 'title' => 'شحن سريع', 'description' => 'الوصف'],
        ]);

        return $service;
    }
}
