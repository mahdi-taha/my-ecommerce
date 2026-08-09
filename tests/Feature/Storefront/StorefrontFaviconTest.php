<?php

namespace Tests\Feature\Storefront;

use App\Models\Setting;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorefrontFaviconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingSeeder::class);
        Storage::fake('public');
    }

    public function test_valid_configured_logo_is_the_shared_storefront_favicon_without_an_extra_setting_query(): void
    {
        Storage::disk('public')->put('store/logo.png', 'logo');
        $this->setLogoPath('store/logo.png');
        $logoQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$logoQueries): void {
            if (str_contains(strtolower($query->sql), 'settings')
                && in_array('store_logo_path', $query->bindings, true)) {
                $logoQueries++;
            }
        });

        $favicon = '<link rel="icon" href="'.Storage::disk('public')->url('store/logo.png').'">';
        $english = $this->get(route('shop.home', ['locale' => 'en']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($english, $favicon));
        $this->assertSame(1, $logoQueries);

        $arabic = $this->get(route('shop.products.index', ['locale' => 'ar']))->assertOk()->getContent();
        $this->assertSame(1, substr_count($arabic, $favicon));
    }

    public function test_empty_or_missing_logo_omits_the_dynamic_favicon(): void
    {
        foreach (['', 'store/missing-logo.png'] as $logoPath) {
            $this->setLogoPath($logoPath);

            $this->get(route('shop.home', ['locale' => 'en']))
                ->assertOk()
                ->assertDontSee('rel="icon"', false)
                ->assertDontSee(Storage::disk('public')->url('store/missing-logo.png'), false);
        }
    }

    private function setLogoPath(string $value): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'store', 'key' => 'store_logo_path'],
            ['value' => $value, 'type' => 'string']
        );
        Cache::forget('setting.store.store_logo_path');
    }
}
