<?php

namespace Tests\Feature\Settings;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventorySettingsCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_obsolete_inventory_settings_are_not_seeded_or_exposed(): void
    {
        $this->assertDatabaseMissing('settings', ['group' => 'inventory', 'key' => 'manage_stock']);
        $this->assertDatabaseMissing('settings', ['group' => 'inventory', 'key' => 'allow_backorders']);

        $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('name="manage_stock"', false)
            ->assertDontSee('name="allow_backorders"', false);
    }

    public function test_obsolete_historical_settings_are_not_updated_by_settings_form(): void
    {
        Setting::query()->create([
            'group' => 'inventory',
            'key' => 'manage_stock',
            'value' => '1',
            'type' => 'boolean',
        ]);
        Setting::query()->create([
            'group' => 'inventory',
            'key' => 'allow_backorders',
            'value' => '0',
            'type' => 'boolean',
        ]);

        $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.settings.update'), array_merge($this->settingsPayload(), [
                'manage_stock' => '0',
                'allow_backorders' => '1',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('settings', [
            'group' => 'inventory',
            'key' => 'manage_stock',
            'value' => '1',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'inventory',
            'key' => 'allow_backorders',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('settings', [
            'group' => 'store',
            'key' => 'store_name',
            'value' => 'Updated Store',
        ]);
    }

    public function test_stock_validation_remains_active_without_inventory_settings(): void
    {
        $product = Product::factory()->create([
            'type' => 'simple',
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->inventory()->create([
            'quantity' => '1.0000',
            'average_cost' => '1.0000',
            'low_stock_alert' => null,
        ]);

        try {
            app(CartService::class)->addSimple(null, bin2hex(random_bytes(32)), $product->id, 2);
            $this->fail('Cart stock validation was disabled by removing obsolete settings.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
            $this->assertSame('1.0000', $product->inventory()->firstOrFail()->quantity);
            $this->assertDatabaseCount('inventory_movements', 0);
        }
    }

    /** @return array<string, mixed> */
    private function settingsPayload(): array
    {
        return [
            'store_name' => 'Updated Store',
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
        ];
    }
}
