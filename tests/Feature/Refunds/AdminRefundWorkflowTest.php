<?php

namespace Tests\Feature\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\ShippingTreatment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class AdminRefundWorkflowTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_admin_can_create_and_view_a_refund(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);

        $response = $this->actingAs($admin, 'admin')->post(route('admin.refunds.store'), [
            'order_id' => $order->id,
            'idempotency_key' => str_repeat('d', 64),
            'items' => [['order_item_id' => $item->id, 'quantity' => '1.0000']],
            'return_shipping_cost' => '0',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
            'customer_note' => 'Refund completed.',
            'internal_note' => 'Private detail.',
        ]);

        $refund = Refund::query()->sole();
        $response->assertRedirect(route('admin.refunds.show', $refund));
        $this->actingAs($admin, 'admin')->get(route('admin.refunds.show', $refund))
            ->assertOk()->assertSee($refund->refund_number)->assertSee('Private detail.');
    }

    public function test_customer_and_guest_cannot_access_admin_refunds(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();

        $this->get(route('admin.refunds.create', ['order' => $order]))->assertRedirect(route('admin.login'));
        $customer = User::factory()->create();
        $this->actingAs($customer, 'customer')->get(route('admin.refunds.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_validation_requires_explicit_shipping_treatment_even_at_zero_cost(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);

        $this->actingAs($admin, 'admin')->post(route('admin.refunds.store'), [
            'order_id' => $order->id,
            'idempotency_key' => str_repeat('e', 64),
            'items' => [['order_item_id' => $item->id, 'quantity' => '1']],
            'return_shipping_cost' => '0',
        ])->assertSessionHasErrors('shipping_treatment');

        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_admin_can_find_eligible_orders_by_number_customer_name_or_email(): void
    {
        [$order, , $admin] = $this->paidRefundOrder([
            'order_number' => 'ORD-LOOKUP-1001',
            'customer_first_name' => 'Lookup',
            'customer_last_name' => 'Customer',
            'customer_email' => 'lookup-customer@example.test',
            'fulfillment_status' => 'out_for_delivery',
            'grand_total' => '123.4500',
            'currency_code' => 'USD',
        ]);
        $this->refundOrderItem($order);

        foreach (['LOOKUP-1001', 'Lookup Customer', 'lookup-customer@example.test'] as $term) {
            $response = $this->actingAs($admin, 'admin')->getJson(route('admin.refunds.lookups.orders', ['q' => $term]));

            $response->assertOk()
                ->assertJsonCount(1)
                ->assertJsonPath('0.order_number', 'ORD-LOOKUP-1001')
                ->assertJsonPath('0.customer_name', 'Lookup Customer')
                ->assertJsonPath('0.customer_email', 'lookup-customer@example.test')
                ->assertJsonPath('0.payment_status', PaymentStatus::Paid->value)
                ->assertJsonPath('0.fulfillment_status', 'out_for_delivery')
                ->assertJsonPath('0.currency_code', 'USD')
                ->assertJsonPath('0.select_url', route('admin.refunds.create', ['order' => $order->id]));
        }
    }

    public function test_lookup_and_create_selection_exclude_clearly_ineligible_orders(): void
    {
        [$order, , $admin] = $this->paidRefundOrder([
            'order_number' => 'ORD-INELIGIBLE-1001',
            'payment_status' => PaymentStatus::Pending->value,
        ]);
        $this->refundOrderItem($order);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.refunds.lookups.orders', ['q' => 'INELIGIBLE-1001']))
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.refunds.create', ['order' => $order->id]))
            ->assertNotFound();
    }

    public function test_direct_order_details_link_still_loads_the_existing_refund_form(): void
    {
        $this->withoutVite();
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order, ['name' => 'Direct Link Item']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.refunds.create', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('Direct Link Item')
            ->assertSee('name="order_id" value="'.$order->id.'"', false)
            ->assertSee('name="items[0][order_item_id]"', false)
            ->assertSee('value="'.$item->id.'"', false)
            ->assertSee('Change Order');
    }
}
