<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_open_english_order_print_and_see_the_details_button(): void
    {
        $admin = User::factory()->create();
        $order = $this->order();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee(route('admin.orders.print', $order), false)
            ->assertSee('target="_blank" rel="noopener"', false);

        $this->get(route('admin.orders.print', $order))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('Order Summary');
    }

    public function test_guest_and_customer_session_cannot_access_admin_print(): void
    {
        $order = $this->order();

        $this->get(route('admin.orders.print', $order))
            ->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('admin.orders.print', $order))
            ->assertRedirect(route('admin.login'));
    }

    private function order(): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-ADMIN-'.fake()->unique()->numerify('######'),
            'customer_email' => 'snapshot@example.test',
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Customer',
            'locale' => 'ar',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ]);
    }
}
