<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_valid_store_logo_is_shared_by_the_admin_favicon_and_topbar(): void
    {
        Storage::disk('public')->put('store/logo.png', 'logo');
        $this->setStoreSetting('store_name', 'Configured Store');
        $this->setStoreSetting('store_logo_path', 'store/logo.png');
        $settingQueries = ['store_name' => 0, 'store_logo_path' => 0];

        DB::listen(function (QueryExecuted $query) use (&$settingQueries): void {
            if (! str_contains(strtolower($query->sql), 'settings')) {
                return;
            }

            foreach (array_keys($settingQueries) as $key) {
                if (in_array($key, $query->bindings, true)) {
                    $settingQueries[$key]++;
                }
            }
        });

        $logoUrl = Storage::disk('public')->url('store/logo.png');
        $favicon = '<link rel="icon" href="'.$logoUrl.'">';
        $content = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.products.index'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($content, $favicon));
        $this->assertStringContainsString('src="'.$logoUrl.'"', $content);
        $this->assertStringContainsString('alt="Configured Store"', $content);
        $this->assertSame(['store_name' => 1, 'store_logo_path' => 1], $settingQueries);
    }

    public function test_empty_or_missing_store_logo_omits_the_admin_favicon(): void
    {
        $admin = User::factory()->create();

        foreach (['', 'store/missing-logo.png'] as $logoPath) {
            $this->setStoreSetting('store_logo_path', $logoPath);

            $this->actingAs($admin, 'admin')
                ->get(route('admin.products.index'))
                ->assertOk()
                ->assertDontSee('rel="icon"', false)
                ->assertDontSee(Storage::disk('public')->url('store/missing-logo.png'), false);
        }
    }

    private function setStoreSetting(string $key, string $value): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'store', 'key' => $key],
            ['value' => $value, 'type' => 'string']
        );
        Cache::forget("setting.store.{$key}");
    }
}
