<?php

namespace Tests\Feature\CustomerAccount;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_cancellation_for_an_owned_eligible_order(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);

        $this->actingAs($customer, 'customer')
            ->post(route('shop.account.orders.cancellation-requests.store', $order), [
                'reason' => 'I ordered the wrong item.',
            ])
            ->assertRedirect(route('shop.account.orders.show', $order));

        $this->assertDatabaseHas('order_cancellation_requests', [
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'status' => 'pending',
            'pending_marker' => true,
        ]);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_duplicate_pending_request_is_rejected_but_rejected_history_allows_a_new_request(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $first = $order->cancellationRequests()->create([
            'user_id' => $customer->id,
            'reason' => 'First reason',
            'status' => 'pending',
            'pending_marker' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->from(route('shop.account.orders.show', $order))
            ->post(route('shop.account.orders.cancellation-requests.store', $order), ['reason' => 'Again'])
            ->assertSessionHasErrors('reason');

        $first->update(['status' => 'rejected', 'pending_marker' => null, 'admin_note' => 'No']);

        $this->post(route('shop.account.orders.cancellation-requests.store', $order), ['reason' => 'New reason'])
            ->assertRedirect(route('shop.account.orders.show', $order));
        $this->assertSame(2, $order->cancellationRequests()->count());
    }

    public function test_customer_cannot_request_another_customers_or_an_ineligible_order(): void
    {
        $customer = User::factory()->customer()->create();
        $otherOrder = $this->order(User::factory()->customer()->create());

        $this->actingAs($customer, 'customer')
            ->post(route('shop.account.orders.cancellation-requests.store', $otherOrder), ['reason' => 'No'])
            ->assertNotFound();

        $completed = $this->order($customer, ['status' => 'completed']);
        $this->post(route('shop.account.orders.cancellation-requests.store', $completed), ['reason' => 'Too late'])
            ->assertSessionHasErrors('reason');
    }

    public function test_guest_is_redirected_and_customer_page_displays_request_history(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $order->cancellationRequests()->create([
            'user_id' => $customer->id,
            'reason' => 'Changed my mind',
            'status' => 'rejected',
            'pending_marker' => null,
            'admin_note' => 'Already packed',
            'reviewed_at' => now(),
        ]);

        $this->post(route('shop.account.orders.cancellation-requests.store', $order), ['reason' => 'Guest'])
            ->assertRedirect(route('customer.login'));

        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Changed my mind')
            ->assertSee('Already packed')
            ->assertSee(__('shop.account.orders.cancellation.request_button'));
    }

    public function test_customer_sees_only_non_empty_processed_notes_for_an_owned_order(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);
        $order->cancellationRequests()->createMany([
            [
                'user_id' => $customer->id,
                'reason' => 'Approved request',
                'status' => 'approved',
                'pending_marker' => null,
                'admin_note' => 'Order cancelled successfully.',
                'reviewed_at' => now(),
            ],
            [
                'user_id' => $customer->id,
                'reason' => 'Rejected request',
                'status' => 'rejected',
                'pending_marker' => null,
                'admin_note' => 'Product has already shipped.',
                'reviewed_at' => now(),
            ],
            [
                'user_id' => $customer->id,
                'reason' => 'Empty note request',
                'status' => 'rejected',
                'pending_marker' => null,
                'admin_note' => null,
                'reviewed_at' => now(),
            ],
            [
                'user_id' => $customer->id,
                'reason' => 'Pending request',
                'status' => 'pending',
                'pending_marker' => true,
                'admin_note' => 'Internal pending text',
            ],
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('shop.account.orders.show', $order));

        $response
            ->assertOk()
            ->assertSeeText(__('shop.account.orders.cancellation.admin_note'))
            ->assertSeeText('Order cancelled successfully.')
            ->assertSeeText('Product has already shipped.')
            ->assertDontSeeText('Internal pending text');

        $otherCustomer = User::factory()->customer()->create();
        $this->actingAs($otherCustomer, 'customer')
            ->get(route('shop.account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_order_without_cancellation_requests_has_no_administrator_note(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertDontSeeText(__('shop.account.orders.cancellation.admin_note'));
    }

    private function order(User $customer, array $state = []): Order
    {
        return Order::query()->create(array_merge([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_first_name' => $customer->first_name,
            'customer_last_name' => $customer->last_name,
            'locale' => 'en', 'currency_code' => 'USD', 'status' => 'pending',
            'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery', 'requires_payment_before_processing' => false,
            'subtotal' => 10, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'grand_total' => 10, 'placed_at' => now(),
        ], $state));
    }
}
