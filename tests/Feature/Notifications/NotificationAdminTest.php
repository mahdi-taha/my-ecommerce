<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationRule;
use App\Models\User;
use Database\Seeders\NotificationConfigurationSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingSeeder::class,
            TaxSeeder::class,
            NotificationConfigurationSeeder::class,
        ]);
    }

    public function test_admin_can_view_and_update_notification_rule_matrix(): void
    {
        $admin = User::factory()->create();
        $rule = NotificationRule::query()
            ->with(['event', 'audience', 'channel'])
            ->firstOrFail();

        $this->actingAs($admin, 'admin')->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Notifications')
            ->assertSee($rule->event->name)
            ->assertSee($rule->audience->name)
            ->assertSee($rule->channel->name);

        $this->put(route('admin.settings.update'), $this->settingsPayload([
            'notification_rules' => [$rule->id],
        ]))->assertRedirect();

        $this->assertDatabaseHas('notification_rules', [
            'id' => $rule->id,
            'is_enabled' => true,
        ]);
        $this->assertSame(1, NotificationRule::query()->where('is_enabled', true)->count());
    }

    public function test_invalid_rule_is_rejected_and_existing_configuration_is_unchanged(): void
    {
        $admin = User::factory()->create();
        $rule = NotificationRule::query()->firstOrFail();
        $rule->update(['is_enabled' => true]);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), $this->settingsPayload([
                'notification_rules' => [PHP_INT_MAX],
            ]))
            ->assertRedirect(route('admin.settings.index'))
            ->assertSessionHasErrors('notification_rules.0');

        $this->assertTrue($rule->fresh()->is_enabled);
    }

    public function test_notification_configuration_requires_active_admin_boundary(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('admin.login'));

        $customer = User::factory()->customer()->create();
        $this->actingAs($customer, 'admin')->get(route('admin.settings.index'))->assertForbidden();
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
            'notification_rules' => [],
        ], $overrides);
    }
}
