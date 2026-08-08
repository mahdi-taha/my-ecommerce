<?php

namespace Tests\Feature\Refunds;

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
}
