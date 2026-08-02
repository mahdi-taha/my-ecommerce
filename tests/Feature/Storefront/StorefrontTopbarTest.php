<?php

namespace Tests\Feature\Storefront;

use App\Enums\NotificationAudienceCode;
use App\Models\DatabaseNotification;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontTopbarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_guest_topbar_uses_store_settings_and_hides_empty_contacts(): void
    {
        $this->setSetting('store', 'store_name', 'Configured Store');
        $this->setSetting('store', 'store_phone', '+961 1 234 567');
        $this->setSetting('store', 'facebook_url', 'https://facebook.com/configured-store');
        $this->setSetting('store', 'whatsapp_url', '');
        $this->setSetting('store', 'instagram_url', 'https://instagram.com/configured-store');
        $this->setSetting('currency', 'default_currency', 'LBP');

        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('Configured Store')
            ->assertSee('href="tel:+9611234567"', false)
            ->assertSee('https://facebook.com/configured-store', false)
            ->assertSee('https://instagram.com/configured-store', false)
            ->assertDontSee('aria-label="'.__('shop.topbar.whatsapp').'"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee(__('shop.topbar.currency_label'))
            ->assertSee('<bdi dir="ltr">LBP</bdi>', false)
            ->assertSee(__('shop.topbar.guest'))
            ->assertSee(route('customer.login'), false)
            ->assertSee(route('customer.register'), false)
            ->assertSee(route('shop.cart.index'), false);
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('method="GET" action="'.route('shop.products.index').'"', false)
            ->assertSee('name="q"', false);
    }

    public function test_authenticated_topbar_uses_customer_routes_and_scoped_notification_count(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Topbar Customer']);
        $other = User::factory()->customer()->create();
        $this->notification($customer, NotificationAudienceCode::Customer->value);
        $this->notification($customer, NotificationAudienceCode::Administrator->value);
        $this->notification($other, NotificationAudienceCode::Customer->value);
        $notificationCountQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$notificationCountQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'database_notifications') && str_contains($sql, 'count(')) {
                $notificationCountQueries++;
            }
        });

        $response = $this->actingAs($customer, 'customer')->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('Topbar Customer')
            ->assertSee(route('customer.account.edit'), false)
            ->assertSee(route('customer.addresses.index'), false)
            ->assertSee(route('shop.account.orders.index'), false)
            ->assertSee(route('shop.wishlist.index'), false)
            ->assertSee(route('shop.account.notifications.index'), false)
            ->assertSee(route('customer.account.password.edit'), false)
            ->assertSee(route('customer.logout'), false)
            ->assertSee('<span class="badge bg-danger rounded-pill">1</span>', false);
        $this->assertSame(1, $notificationCountQueries);
    }

    public function test_locale_switch_is_session_backed_local_and_does_not_change_admin_locale(): void
    {
        $this->post(route('shop.locale.update', 'ar'), ['return_to' => '/login?source=topbar'])
            ->assertRedirect('/login?source=topbar')
            ->assertSessionHas('storefront_locale', 'ar');

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);

        $this->post(route('shop.locale.update', 'en'), ['return_to' => 'https://example.test/escape'])
            ->assertRedirect('/');
        $this->post(route('shop.locale.update', 'fr'), ['return_to' => '/'])->assertNotFound();

        $admin = User::factory()->create();
        $this->withSession(['storefront_locale' => 'ar'])
            ->actingAs($admin, 'admin')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('<html lang="en">', false);
    }

    public function test_default_storefront_locale_is_used_until_the_customer_selects_one(): void
    {
        $this->setSetting('localization', 'default_locale', 'ar');

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);

        $this->post(route('shop.locale.update', 'en'), ['return_to' => '/login']);

        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    private function setSetting(string $group, string $key, ?string $value): void
    {
        Setting::query()->where('group', $group)->where('key', $key)->update(['value' => $value]);
        Cache::forget("setting.{$group}.{$key}");
    }

    private function notification(User $user, string $audience): void
    {
        DatabaseNotification::query()->create([
            'audience_code' => $audience,
            'user_id' => $user->id,
            'event_code' => 'order_placed',
            'entity_type' => 'order',
            'entity_id' => 1,
            'title' => 'Topbar notification',
            'body' => 'Notification body.',
            'payload' => ['order_id' => 1],
            'created_at' => now(),
        ]);
    }
}
