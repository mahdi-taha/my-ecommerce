<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCancellationRequestService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderCancellationRequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_failure_rolls_back_request_approval_with_the_order(): void
    {
        $admin = User::factory()->create();
        [$order, $request] = $this->orderWithRequest();

        try {
            app(OrderCancellationRequestService::class)->approve($order, $request, $admin);
            $this->fail('Cancellation without a payment obligation was accepted.');
        } catch (\RuntimeException) {
            $this->assertSame('pending', $order->fresh()->status);
            $this->assertSame('pending', $request->fresh()->status->value);
            $this->assertTrue($request->fresh()->pending_marker);
            $this->assertDatabaseCount('order_status_history', 0);
        }
    }

    public function test_database_enforces_one_pending_request_and_valid_marker_state(): void
    {
        [$order, $request] = $this->orderWithRequest();

        try {
            $order->cancellationRequests()->create([
                'user_id' => $order->user_id, 'reason' => 'Duplicate',
                'status' => 'pending', 'pending_marker' => true,
            ]);
            $this->fail('A second pending cancellation request was stored.');
        } catch (QueryException) {
            $this->assertDatabaseCount('order_cancellation_requests', 1);
        }

        $request->update(['status' => 'rejected', 'pending_marker' => null, 'admin_note' => 'No']);
        $order->cancellationRequests()->create([
            'user_id' => $order->user_id, 'reason' => 'New request',
            'status' => 'pending', 'pending_marker' => true,
        ]);
        $this->assertDatabaseCount('order_cancellation_requests', 2);
    }

    public function test_paid_order_remains_pending_when_approval_is_rejected(): void
    {
        $admin = User::factory()->create();
        [$order, $request] = $this->orderWithRequest(['payment_status' => 'paid']);

        $this->expectException(ValidationException::class);

        try {
            app(OrderCancellationRequestService::class)->approve($order, $request, $admin);
        } finally {
            $this->assertSame('pending', $request->fresh()->status->value);
            $this->assertSame('pending', $order->fresh()->status);
        }
    }

    private function orderWithRequest(array $state = []): array
    {
        $customer = User::factory()->customer()->create();
        $order = Order::query()->create(array_merge([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'user_id' => $customer->id, 'customer_email' => $customer->email,
            'customer_first_name' => $customer->first_name, 'customer_last_name' => $customer->last_name,
            'locale' => 'en', 'currency_code' => 'USD', 'status' => 'pending',
            'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery', 'requires_payment_before_processing' => false,
            'subtotal' => 10, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'grand_total' => 10, 'placed_at' => now(),
        ], $state));
        $request = $order->cancellationRequests()->create([
            'user_id' => $customer->id, 'reason' => 'Cancel it',
            'status' => 'pending', 'pending_marker' => true,
        ]);

        return [$order, $request];
    }
}
