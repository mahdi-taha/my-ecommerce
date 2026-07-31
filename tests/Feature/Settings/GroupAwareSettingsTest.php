<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GroupAwareSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_settings_page_reads_the_explicit_group_when_keys_are_duplicated(): void
    {
        Setting::query()
            ->where('group', 'store')
            ->where('key', 'store_name')
            ->update(['value' => 'Authoritative Store']);
        Setting::query()->create([
            'group' => 'other',
            'key' => 'store_name',
            'value' => 'Unrelated Store',
            'type' => 'text',
        ]);

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.settings.index'));

        $response->assertOk();
        $this->assertSame('Authoritative Store', $response->viewData('settings')['store_name']);
    }

    public function test_update_targets_only_the_mapped_group_and_invalidates_only_changed_cache(): void
    {
        Setting::query()->create([
            'group' => 'other',
            'key' => 'store_name',
            'value' => 'Unrelated Store',
            'type' => 'text',
        ]);

        $this->assertSame('My Store', setting('store.store_name'));
        $this->assertSame('Unrelated Store', setting('other.store_name'));
        $this->assertSame('en', setting('localization.default_locale'));

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.settings.update'), $this->settingsPayload([
                'store_name' => 'Updated Store',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'group' => 'store',
            'key' => 'store_name',
            'value' => 'Updated Store',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'other',
            'key' => 'store_name',
            'value' => 'Unrelated Store',
        ]);
        $this->assertFalse(Cache::has('setting.store.store_name'));
        $this->assertTrue(Cache::has('setting.other.store_name'));
        $this->assertTrue(Cache::has('setting.localization.default_locale'));
        $this->assertSame('Updated Store', setting('store.store_name'));
        $this->assertSame('Unrelated Store', setting('other.store_name'));
    }

    /** @param array<string, mixed> $overrides */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'My Store',
            'store_email' => null,
            'store_phone' => null,
            'store_address' => null,
            'default_locale' => 'en',
            'timezone' => 'Asia/Beirut',
            'default_currency' => 'USD',
            'tax_mode' => 'b2c',
            'default_tax_id' => null,
            'manage_stock' => '1',
            'allow_backorders' => '0',
            'allow_guest_checkout' => '1',
            'notification_rules' => [],
        ], $overrides);
    }
}
