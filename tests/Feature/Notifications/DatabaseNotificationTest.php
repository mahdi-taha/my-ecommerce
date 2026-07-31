<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationEventCode;
use App\Events\CommerceEventOccurred;
use App\Models\DatabaseNotification;
use App\Models\NotificationRule;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationConfigurationService;
use Database\Seeders\NotificationConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class DatabaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_rule_creates_nothing_and_enabled_rule_creates_localized_customer_notification(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer, 'ar');

        CommerceEventOccurred::dispatch(NotificationEventCode::OrderPlaced, 'order', $order->id);
        $this->assertDatabaseCount('database_notifications', 0);

        $this->enable('order_placed', 'customer');
        CommerceEventOccurred::dispatch(NotificationEventCode::OrderPlaced, 'order', $order->id);

        $notification = DatabaseNotification::query()->sole();
        $this->assertSame($customer->id, $notification->user_id);
        $this->assertSame('customer', $notification->audience_code);
        $this->assertSame('order_placed', $notification->event_code);
        $this->assertStringContainsString($order->order_number, $notification->body);
        $this->assertSame('تم تقديم الطلب', $notification->title);
    }

    public function test_guest_receives_nothing_and_each_active_eligible_admin_receives_one_row(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $activeAdmins = User::factory()->count(2)->create();
        User::factory()->inactive()->create();
        User::factory()->create(['has_account' => false]);
        $guestOrder = $this->order(null);
        $this->enable('order_placed', 'customer', 'administrator');

        CommerceEventOccurred::dispatch(NotificationEventCode::OrderPlaced, 'order', $guestOrder->id);

        $this->assertDatabaseCount('database_notifications', 2);
        $this->assertEqualsCanonicalizing(
            $activeAdmins->pluck('id')->all(),
            DatabaseNotification::query()->pluck('user_id')->all()
        );
        $this->assertDatabaseMissing('database_notifications', ['audience_code' => 'customer']);
    }

    public function test_notification_content_is_immutable_and_only_read_at_may_change(): void
    {
        $user = User::factory()->customer()->create();
        $notification = $this->notification($user);
        $notification->update(['read_at' => now()]);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->expectException(LogicException::class);
        $notification->update(['title' => 'Changed']);
    }

    public function test_rollback_creates_no_delivery_rows(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $this->enable('order_placed', 'customer');

        try {
            DB::transaction(function () use ($order): void {
                CommerceEventOccurred::dispatch(NotificationEventCode::OrderPlaced, 'order', $order->id);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Expected rollback.
        }

        $this->assertDatabaseCount('database_notifications', 0);
    }

    private function enable(string $event, string ...$audiences): void
    {
        $ids = NotificationRule::query()
            ->whereHas('event', fn ($query) => $query->where('code', $event))
            ->whereHas('channel', fn ($query) => $query->where('code', 'database'))
            ->whereHas('audience', fn ($query) => $query->whereIn('code', $audiences))
            ->pluck('id')
            ->all();

        app(NotificationConfigurationService::class)->updateEnabledRules($ids);
    }

    private function order(?User $customer, string $locale = 'en'): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'guest@example.com',
            'customer_first_name' => $customer?->first_name ?? 'Guest',
            'customer_last_name' => $customer?->last_name ?? 'Customer',
            'locale' => $locale,
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
            'placed_at' => now(),
        ]);
    }

    private function notification(User $user): DatabaseNotification
    {
        return DatabaseNotification::query()->create([
            'audience_code' => 'customer',
            'user_id' => $user->id,
            'event_code' => 'order_placed',
            'entity_type' => 'order',
            'entity_id' => 1,
            'title' => 'Order placed',
            'body' => 'Order placed.',
            'payload' => ['order_id' => 1],
            'created_at' => now(),
        ]);
    }
}
