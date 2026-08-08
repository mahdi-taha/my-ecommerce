<?php

namespace Tests\Feature\Refunds;

use App\Enums\AccountType;
use App\Enums\ShippingTreatment;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class CustomerRefundHistoryTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_customer_sees_safe_refund_history_and_only_positive_shipping_deductions(): void
    {
        $customer = User::factory()->create(['account_type' => AccountType::Customer, 'is_active' => true]);
        [$order, , $admin] = $this->paidRefundOrder(['user_id' => $customer->id]);
        $item = $this->refundOrderItem($order);
        $refund = app(RefundService::class)->create($order, $admin, [
            'items' => [['order_item_id' => $item->id, 'quantity' => '1']],
            'return_shipping_cost' => '5',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
            'customer_note' => 'Visible note',
            'internal_note' => 'Secret note',
        ], str_repeat('f', 64));

        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', ['locale' => 'en', 'order' => $order]))
            ->assertOk()->assertSee($refund->refund_number)->assertSee('Visible note')
            ->assertDontSee('Secret note')->assertDontSee('Company shipping loss')
            ->assertDontSee('Return Shipping Deduction');
    }
}
