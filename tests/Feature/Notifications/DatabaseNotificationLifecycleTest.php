<?php

namespace Tests\Feature\Notifications;

use App\DTOs\Notifications\NotificationDispatchDecision;
use App\Events\NotificationDispatchResolved;
use App\Models\DatabaseNotification;
use App\Models\Order;
use App\Models\OrderCancellationRequest;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\NotificationMessageBuilder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseNotificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_supported_lifecycle_events_build_database_notifications(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $payment = $this->payment($order);
        $cancellationRequest = $this->cancellationRequest($order, $customer);

        $events = [
            ['order_placed', 'order', $order->id],
            ['order_completed', 'order', $order->id],
            ['order_cancelled', 'order', $order->id],
            ['delivery_failed', 'order', $order->id],
            ['payment_paid', 'order_payment', $payment->id],
            ['payment_failed', 'order_payment', $payment->id],
            ['payment_cancelled', 'order_payment', $payment->id],
            ['cancellation_request_submitted', 'order_cancellation_request', $cancellationRequest->id],
            ['cancellation_request_approved', 'order_cancellation_request', $cancellationRequest->id],
            ['cancellation_request_rejected', 'order_cancellation_request', $cancellationRequest->id],
        ];

        foreach ($events as [$event, $entityType, $entityId]) {
            NotificationDispatchResolved::dispatch($this->decision($event, $entityType, $entityId));
        }

        $this->assertDatabaseCount('database_notifications', 10);
        $this->assertEqualsCanonicalizing(
            array_column($events, 0),
            DatabaseNotification::query()->pluck('event_code')->all()
        );
        $this->assertTrue(DatabaseNotification::query()->get()->every(
            fn (DatabaseNotification $notification) => str_contains($notification->body, $order->order_number)
        ));
    }

    public function test_deferred_events_are_ignored_by_database_delivery(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);

        NotificationDispatchResolved::dispatch($this->decision('payment_refunded', 'order_payment', 999));
        NotificationDispatchResolved::dispatch($this->decision('coupon_applied', 'order', $order->id));

        $this->assertDatabaseCount('database_notifications', 0);
    }

    public function test_delivery_failure_is_swallowed_after_commerce_has_committed(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $builder = $this->mock(NotificationMessageBuilder::class);
        $builder->shouldReceive('resolveContext')->once()->andThrow(new \RuntimeException('delivery unavailable'));

        NotificationDispatchResolved::dispatch($this->decision('order_placed', 'order', $order->id));

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertDatabaseCount('database_notifications', 0);
    }

    public function test_entity_context_is_resolved_once_and_reused_for_all_recipients_and_locales(): void
    {
        $customer = User::factory()->customer()->create();
        User::factory()->count(3)->create();
        $order = $this->order($customer);
        $payment = $this->payment($order);
        $cancellationRequest = $this->cancellationRequest($order, $customer);
        $queryCounts = [];

        DB::listen(function (QueryExecuted $query) use (&$queryCounts): void {
            foreach (['orders', 'order_payments', 'order_cancellation_requests'] as $table) {
                if (preg_match('/from\s+[`"]?'.preg_quote($table, '/').'[`"]?/i', $query->sql) === 1) {
                    $queryCounts[$table] = ($queryCounts[$table] ?? 0) + 1;
                }
            }
        });

        $cases = [
            ['order_placed', 'order', $order->id, ['orders' => 1]],
            ['payment_paid', 'order_payment', $payment->id, ['order_payments' => 1, 'orders' => 1]],
            [
                'cancellation_request_submitted',
                'order_cancellation_request',
                $cancellationRequest->id,
                ['order_cancellation_requests' => 1, 'orders' => 1],
            ],
        ];

        foreach ($cases as [$event, $entityType, $entityId, $expectedQueries]) {
            $queryCounts = [];

            NotificationDispatchResolved::dispatch(
                $this->decision($event, $entityType, $entityId, ['customer', 'administrator'])
            );

            $this->assertSame($expectedQueries, $queryCounts);
        }
    }

    private function decision(
        string $event,
        string $entityType,
        int $entityId,
        array $audiences = ['customer']
    ): NotificationDispatchDecision {
        return new NotificationDispatchDecision(
            event: $event,
            entityType: $entityType,
            entityId: $entityId,
            audiences: $audiences,
            channels: ['database'],
            rules: collect($audiences)
                ->map(fn (string $audience): array => ['audience' => $audience, 'channel' => 'database'])
                ->all(),
            enabled: true,
        );
    }

    private function order(User $customer): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-2026-880001',
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'locale' => 'en',
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

    private function payment(Order $order): OrderPayment
    {
        return OrderPayment::query()->create([
            'payment_number' => 'PAY-2026-880001',
            'order_id' => $order->id,
            'payment_method_id' => null,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => 10,
            'currency_code' => 'USD',
            'status' => 'pending',
            'paid_amount' => 0,
        ]);
    }

    private function cancellationRequest(Order $order, User $customer): OrderCancellationRequest
    {
        return OrderCancellationRequest::query()->create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'reason' => 'Changed my mind',
            'status' => 'pending',
            'pending_marker' => true,
        ]);
    }
}
