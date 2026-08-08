<?php

namespace Tests\Feature\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\ShippingTreatment;
use App\Models\OrderStatusHistory;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class RefundLifecycleTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_merchandise_exhaustion_marks_payment_refunded_despite_shipping_deduction(): void
    {
        [$order, $payment, $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order, ['quantity' => '2.0000']);
        $paidAt = $payment->paid_at;

        $service = app(RefundService::class);
        $first = $service->create($order, $admin, $this->input($item->id, '1.0000'), str_repeat('a', 64));
        $second = $service->create($order, $admin, $this->input(
            $item->id,
            '1.0000',
            '10.0000',
            ShippingTreatment::DeductFromRefund,
        ), str_repeat('b', 64));

        $this->assertSame('50.0000', $first->customer_refund_amount);
        $this->assertSame('40.0000', $second->customer_refund_amount);
        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
        $this->assertSame('100.0000', $payment->fresh()->paid_amount);
        $this->assertTrue($paidAt->equalTo($payment->fresh()->paid_at));
        $this->assertSame(PaymentStatus::Refunded->value, $order->fresh()->payment_status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('fulfilled', $order->fresh()->fulfillment_status);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertSame(2, OrderStatusHistory::query()->where('type', 'payment')->count());
    }

    public function test_idempotency_token_replays_the_same_refund_without_a_second_transition(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);
        $token = str_repeat('c', 64);
        $service = app(RefundService::class);

        $first = $service->create($order, $admin, $this->input($item->id), $token);
        $replay = $service->create($order, $admin, $this->input($item->id), $token);

        $this->assertTrue($first->is($replay));
        $this->assertSame(1, Refund::query()->count());
        $this->assertSame(1, OrderStatusHistory::query()->where('type', 'payment')->count());
    }

    /** @return array<string, mixed> */
    private function input(
        int $itemId,
        string $quantity = '1.0000',
        string $shipping = '0.0000',
        ShippingTreatment $treatment = ShippingTreatment::CompanyAbsorbs,
    ): array {
        return [
            'items' => [['order_item_id' => $itemId, 'quantity' => $quantity]],
            'return_shipping_cost' => $shipping,
            'shipping_treatment' => $treatment->value,
        ];
    }
}
