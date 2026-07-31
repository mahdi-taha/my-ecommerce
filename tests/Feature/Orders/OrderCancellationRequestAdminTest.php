<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_a_pending_request_through_existing_cancellation_lifecycle(): void
    {
        $admin = User::factory()->create();
        [$order, $request] = $this->orderWithRequest();
        OrderPayment::query()->create([
            'payment_number' => 'PAY-2026-900001',
            'order_id' => $order->id,
            'payment_method_id' => null,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => 'pending',
            'paid_amount' => '0.0000',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancellation-requests.approve', [$order, $request]))
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('order_cancellation_requests', [
            'id' => $request->id, 'status' => 'approved',
            'pending_marker' => null, 'reviewed_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id, 'type' => 'order', 'to_status' => 'cancelled',
        ]);
    }

    public function test_admin_can_reject_with_a_required_note_without_changing_order(): void
    {
        $admin = User::factory()->create();
        [$order, $request] = $this->orderWithRequest();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancellation-requests.reject', [$order, $request]), [])
            ->assertSessionHasErrors('admin_note');

        $this->post(route('admin.orders.cancellation-requests.reject', [$order, $request]), [
            'admin_note' => 'The parcel has already been prepared.',
        ])->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseHas('order_cancellation_requests', [
            'id' => $request->id, 'status' => 'rejected',
            'admin_note' => 'The parcel has already been prepared.',
        ]);
    }

    public function test_terminal_requests_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()->create();
        [$order, $request] = $this->orderWithRequest();
        $request->update(['status' => 'rejected', 'pending_marker' => null, 'admin_note' => 'Rejected']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancellation-requests.approve', [$order, $request]))
            ->assertSessionHas('error');
        $this->post(route('admin.orders.cancellation-requests.reject', [$order, $request]), [
            'admin_note' => 'Again',
        ])->assertSessionHas('error');

        $this->assertSame('rejected', $request->fresh()->status->value);
    }

    public function test_direct_admin_cancellation_is_blocked_while_request_is_pending(): void
    {
        $admin = User::factory()->create();
        [$order] = $this->orderWithRequest();

        $this->actingAs($admin, 'admin')->post(route('admin.orders.cancel', $order))
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
    }

    private function orderWithRequest(): array
    {
        $customer = User::factory()->customer()->create();
        $order = Order::query()->create([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'user_id' => $customer->id, 'customer_email' => $customer->email,
            'customer_first_name' => $customer->first_name, 'customer_last_name' => $customer->last_name,
            'locale' => 'en', 'currency_code' => 'USD', 'status' => 'pending',
            'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery', 'requires_payment_before_processing' => false,
            'subtotal' => 10, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'grand_total' => 10, 'placed_at' => now(),
        ]);
        $request = $order->cancellationRequests()->create([
            'user_id' => $customer->id, 'reason' => 'Please cancel',
            'status' => 'pending', 'pending_marker' => true,
        ]);

        return [$order, $request];
    }
}
