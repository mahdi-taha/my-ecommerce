<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationEventCode;
use App\Events\CommerceEventOccurred;
use App\Events\NotificationDispatchResolved;
use App\Models\NotificationRule;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationConfigurationService;
use App\Services\NotificationEventService;
use App\Services\OrderCancellationRequestService;
use Database\Seeders\NotificationConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class NotificationTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_occurs_only_after_commit_and_rollback_emits_nothing(): void
    {
        $this->seed(NotificationConfigurationSeeder::class);
        $rule = NotificationRule::query()
            ->whereHas('event', fn ($query) => $query->where('code', 'order_placed'))
            ->firstOrFail();
        app(NotificationConfigurationService::class)->updateEnabledRules([$rule->id]);
        Event::fake([NotificationDispatchResolved::class]);

        DB::transaction(function (): void {
            CommerceEventOccurred::dispatch(NotificationEventCode::OrderPlaced, 'order', 123);
            Event::assertNotDispatched(NotificationDispatchResolved::class);
        });

        Event::assertDispatched(NotificationDispatchResolved::class, fn ($event) => $event->decision->entityId === 123 && $event->decision->enabled
        );

        Event::fake([NotificationDispatchResolved::class]);

        try {
            DB::transaction(function (): void {
                CommerceEventOccurred::dispatch(NotificationEventCode::OrderPlaced, 'order', 456);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            Event::assertNotDispatched(NotificationDispatchResolved::class);
        }
    }

    public function test_resolution_failure_is_logged_and_does_not_fail_customer_request_creation(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $resolver = $this->mock(NotificationEventService::class);
        $resolver->shouldReceive('resolve')->once()->andThrow(new RuntimeException('unavailable'));
        Log::shouldReceive('error')->once();

        $request = app(OrderCancellationRequestService::class)->create(
            $order,
            $customer,
            'Changed my mind'
        );

        $this->assertDatabaseHas('order_cancellation_requests', [
            'id' => $request->id,
            'status' => 'pending',
        ]);
    }

    private function order(User $customer): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-2026-990001',
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'locale' => 'en', 'currency_code' => 'USD', 'status' => 'pending',
            'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery', 'requires_payment_before_processing' => false,
            'subtotal' => 10, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'grand_total' => 10, 'placed_at' => now(),
        ]);
    }
}
