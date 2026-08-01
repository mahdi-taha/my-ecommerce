<?php

namespace Tests\Feature\Settings;

use App\Models\NotificationRule;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\NotificationConfigurationSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\TaxSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SettingsLongTextMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_values_nullability_and_unique_identity_survive_the_transition(): void
    {
        Schema::drop('settings');
        $this->originalSettingsMigration()->up();

        DB::table('settings')->insert([
            [
                'group' => 'migration',
                'key' => 'existing',
                'value' => 'preserved-before-longtext',
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'migration',
                'key' => 'nullable',
                'value' => null,
                'type' => 'text',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->longTextMigration()->up();

        $this->assertDatabaseHas('settings', [
            'group' => 'migration',
            'key' => 'existing',
            'value' => 'preserved-before-longtext',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'migration',
            'key' => 'nullable',
            'value' => null,
        ]);

        $columnType = strtolower(Schema::getColumnType('settings', 'value', true));
        if (DB::getDriverName() === 'mysql') {
            $this->assertStringContainsString('longtext', $columnType);
        } else {
            $this->assertStringContainsString('text', $columnType);
        }

        try {
            Setting::query()->create([
                'group' => 'migration',
                'key' => 'existing',
                'value' => 'duplicate',
                'type' => 'text',
            ]);
            $this->fail('The Settings group/key unique constraint was not preserved.');
        } catch (QueryException) {
            $this->assertSame(1, Setting::query()
                ->where('group', 'migration')
                ->where('key', 'existing')
                ->count());
        }
    }

    public function test_large_text_and_json_values_are_stored_and_resolved(): void
    {
        $largeText = str_repeat('T', 70_000);
        $largeJsonValue = ['payload' => str_repeat('J', 70_000)];
        $largeJson = json_encode($largeJsonValue, JSON_THROW_ON_ERROR);

        Setting::query()->create([
            'group' => 'large',
            'key' => 'text',
            'value' => $largeText,
            'type' => 'text',
        ]);
        Setting::query()->create([
            'group' => 'large',
            'key' => 'json',
            'value' => $largeJson,
            'type' => 'json',
        ]);
        Cache::forget('setting.large.text');
        Cache::forget('setting.large.json');

        $this->assertSame($largeText, setting('large.text'));
        $this->assertSame($largeJsonValue, setting('large.json'));
    }

    public function test_normal_settings_and_notification_configuration_updates_are_unchanged(): void
    {
        $this->seed([
            SettingSeeder::class,
            TaxSeeder::class,
            NotificationConfigurationSeeder::class,
        ]);
        $rule = NotificationRule::query()->firstOrFail();

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.settings.update'), $this->settingsPayload([
                'store_name' => 'LongText Store',
                'notification_rules' => [$rule->id],
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'group' => 'store',
            'key' => 'store_name',
            'value' => 'LongText Store',
        ]);
        $this->assertTrue($rule->fresh()->is_enabled);
    }

    public function test_migration_is_explicitly_forward_only(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('narrowing to TEXT could truncate existing data');

        $this->longTextMigration()->down();
    }

    private function originalSettingsMigration(): object
    {
        return require database_path('migrations/2026_07_16_192710_create_settings_table.php');
    }

    private function longTextMigration(): object
    {
        return require database_path('migrations/2026_08_01_000004_align_settings_value_longtext.php');
    }

    /** @param array<string, mixed> $overrides */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'store_name' => 'Store',
            'store_email' => null,
            'store_phone' => null,
            'store_address' => null,
            'default_locale' => 'en',
            'timezone' => 'Asia/Beirut',
            'default_currency' => 'USD',
            'tax_mode' => 'b2c',
            'default_tax_id' => null,
            'allow_guest_checkout' => '1',
            'notification_rules' => [],
        ], $overrides);
    }
}
