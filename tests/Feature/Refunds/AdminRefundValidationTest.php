<?php

namespace Tests\Feature\Refunds;

use App\Enums\ShippingTreatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class AdminRefundValidationTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_required_refund_fields_use_human_friendly_messages(): void
    {
        [, , $admin] = $this->paidRefundOrder();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.refunds.store'), []);

        $response
            ->assertSessionHasErrors([
                'order_id' => 'Please select an order.',
                'items' => 'Please select at least one item to refund.',
                'return_shipping_cost' => 'Please enter the return shipping cost.',
                'shipping_treatment' => 'Please choose how return shipping should be handled.',
            ]);

        $messages = implode(' ', session('errors')->all());
        $this->assertStringNotContainsString('order_id', $messages);
        $this->assertStringNotContainsString('items.', $messages);
        $this->assertStringNotContainsString('items.*', $messages);
    }

    public function test_missing_quantity_renders_without_an_array_index_and_preserves_old_input(): void
    {
        $this->withoutVite();
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.refunds.create', ['order' => $order->id]))
            ->followingRedirects()
            ->post(route('admin.refunds.store'), [
                'order_id' => $order->id,
                'idempotency_key' => str_repeat('a', 64),
                'items' => [[
                    'order_item_id' => $item->id,
                    'quantity' => '',
                ]],
                'return_shipping_cost' => '0.0000',
                'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
                'reason' => 'Keep this reason',
            ]);

        $response
            ->assertOk()
            ->assertSee('Please enter a refund quantity.')
            ->assertDontSee('items.0.quantity')
            ->assertDontSee('items.*.quantity')
            ->assertSee('value="Keep this reason"', false);
    }

    public function test_invalid_item_and_shipping_values_never_expose_internal_paths(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.refunds.store'), [
            'order_id' => $order->id,
            'idempotency_key' => str_repeat('b', 64),
            'items' => [[
                'order_item_id' => 'not-an-id',
                'quantity' => 'invalid',
            ]],
            'return_shipping_cost' => 'invalid',
            'shipping_treatment' => 'invalid',
        ]);

        $messages = implode(' ', session('errors')->all());
        $this->assertStringContainsString('A selected refund item is invalid.', $messages);
        $this->assertStringContainsString('Please enter a valid refund quantity with up to 4 decimal places.', $messages);
        $this->assertStringContainsString('Please choose a valid return shipping treatment.', $messages);
        $this->assertStringNotContainsString('items.0', $messages);
        $this->assertStringNotContainsString('order_item_id', $messages);
        $this->assertStringNotContainsString('shipping_treatment', $messages);
    }
}
