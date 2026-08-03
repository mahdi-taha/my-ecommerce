<?php

namespace Tests\Feature\Cms;

use App\Enums\HomepageServiceIcon;
use App\Models\HomepageService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageServiceAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_deactivate_and_delete_a_service(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.homepage-services.store'), $this->payload())
            ->assertRedirect(route('admin.homepage-services.index'));

        $service = HomepageService::query()->with('translations')->firstOrFail();
        $this->assertTrue($service->is_active);
        $this->assertSame(HomepageServiceIcon::Shipping, $service->icon);
        $this->assertSame('Fast Shipping', $service->translations->firstWhere('locale', 'en')->title);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.homepage-services.update', $service), $this->payload([
                'is_active' => 0,
                'title_en' => 'Updated Shipping',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($service->fresh()->is_active);
        $this->assertSame('Updated Shipping', $service->translations()->where('locale', 'en')->value('title'));

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.homepage-services.destroy', $service))
            ->assertRedirect(route('admin.homepage-services.index'));
        $this->assertDatabaseMissing('homepage_services', ['id' => $service->id]);
        $this->assertDatabaseMissing('homepage_service_translations', ['homepage_service_id' => $service->id]);
    }

    public function test_only_six_services_can_be_active_and_an_active_update_does_not_count_itself(): void
    {
        $admin = User::factory()->create();
        foreach (range(1, 6) as $position) {
            $this->actingAs($admin, 'admin')->post(
                route('admin.homepage-services.store'),
                $this->payload(['sort_order' => $position, 'title_en' => "Service {$position}"])
            )->assertSessionHasNoErrors();
        }

        $first = HomepageService::query()->firstOrFail();
        $this->actingAs($admin, 'admin')->put(
            route('admin.homepage-services.update', $first),
            $this->payload(['title_en' => 'Still Active'])
        )->assertSessionHasNoErrors();

        $this->actingAs($admin, 'admin')->post(
            route('admin.homepage-services.store'),
            $this->payload(['title_en' => 'Seventh'])
        )->assertSessionHasErrors('is_active');

        $inactive = HomepageService::query()->create([
            'icon' => HomepageServiceIcon::Support,
            'is_active' => false,
            'sort_order' => 20,
        ]);
        foreach (['en', 'ar'] as $locale) {
            $inactive->translations()->create([
                'locale' => $locale,
                'title' => 'Inactive',
                'description' => 'Inactive description',
            ]);
        }

        $this->actingAs($admin, 'admin')->put(
            route('admin.homepage-services.update', $inactive),
            $this->payload(['title_en' => 'Activate Existing'])
        )->assertSessionHasErrors('is_active');
        $this->assertSame(6, HomepageService::query()->active()->count());
    }

    public function test_requests_enforce_icon_and_bilingual_content_boundaries(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin')->post(route('admin.homepage-services.store'), $this->payload([
            'icon' => '<script>alert(1)</script>',
            'title_ar' => '',
            'description_en' => str_repeat('x', 501),
        ]))->assertSessionHasErrors(['icon', 'title_ar', 'description_en']);

        $this->assertDatabaseCount('homepage_services', 0);
    }

    public function test_admin_routes_require_admin_authentication(): void
    {
        $this->get(route('admin.homepage-services.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.homepage-services.store'), $this->payload())->assertRedirect(route('admin.login'));
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'icon' => HomepageServiceIcon::Shipping->value,
            'is_active' => 1,
            'sort_order' => 1,
            'title_en' => 'Fast Shipping',
            'description_en' => 'Reliable shipping for every order.',
            'title_ar' => 'شحن سريع',
            'description_ar' => 'شحن موثوق لكل طلب.',
        ], $overrides);
    }
}
