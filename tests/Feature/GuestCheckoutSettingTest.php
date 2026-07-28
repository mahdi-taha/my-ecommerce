<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuestCheckoutSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_checkout_setting_is_seeded_enabled_by_default(): void
    {
        $this->seed(SettingSeeder::class);

        $this->assertDatabaseHas('settings', [
            'group' => 'checkout',
            'key' => 'allow_guest_checkout',
            'value' => '1',
            'type' => 'boolean',
        ]);
        $this->assertTrue(setting('checkout.allow_guest_checkout'));
    }

    public function test_admin_can_update_guest_checkout_and_cache_is_invalidated_immediately(): void
    {
        $this->seed(SettingSeeder::class);
        $this->assertTrue(setting('checkout.allow_guest_checkout'));
        $this->assertTrue(Cache::has('setting.checkout.allow_guest_checkout'));

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->put(route('admin.settings.update'), $this->settingsPayload([
                'allow_guest_checkout' => '0',
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('settings', [
            'group' => 'checkout',
            'key' => 'allow_guest_checkout',
            'value' => '',
        ]);
        $this->assertFalse(Cache::has('setting.checkout.allow_guest_checkout'));
        $this->assertFalse(setting('checkout.allow_guest_checkout'));
    }

    public function test_guest_checkout_setting_rejects_invalid_boolean_values(): void
    {
        $this->seed(SettingSeeder::class);

        $this->actingAs(User::factory()->create(), 'admin')
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), $this->settingsPayload([
                'allow_guest_checkout' => 'sometimes',
            ]))
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('allow_guest_checkout');

        $this->assertTrue((bool) Setting::where('group', 'checkout')
            ->where('key', 'allow_guest_checkout')
            ->value('value'));
    }

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
            'manage_stock' => '1',
            'allow_backorders' => '0',
            'allow_guest_checkout' => '1',
        ], $overrides);
    }
}
