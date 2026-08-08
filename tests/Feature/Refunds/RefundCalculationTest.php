<?php

namespace Tests\Feature\Refunds;

use App\Enums\ShippingTreatment;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class RefundCalculationTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_quote_uses_immutable_line_snapshots_and_shipping_formulas(): void
    {
        [$order] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order, [
            'quantity' => '2.0000',
            'row_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
            'tax_amount' => '9.0000',
            'row_total' => '99.0000',
        ]);

        $absorbed = app(RefundService::class)->quote($order, [
            'items' => [['order_item_id' => $item->id, 'quantity' => '1.0000']],
            'return_shipping_cost' => '5.0000',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
        ]);
        $this->assertSame('50.0000', $absorbed['merchandise_subtotal']);
        $this->assertSame('5.0000', $absorbed['discount_amount']);
        $this->assertSame('4.5000', $absorbed['tax_amount']);
        $this->assertSame('49.5000', $absorbed['merchandise_amount']);
        $this->assertSame('0.0000', $absorbed['shipping_deduction']);
        $this->assertSame('5.0000', $absorbed['company_shipping_loss']);
        $this->assertSame('49.5000', $absorbed['customer_refund_amount']);

        $deducted = app(RefundService::class)->quote($order, [
            'items' => [['order_item_id' => $item->id, 'quantity' => '1.0000']],
            'return_shipping_cost' => '5.0000',
            'shipping_treatment' => ShippingTreatment::DeductFromRefund->value,
        ]);
        $this->assertSame('5.0000', $deducted['shipping_deduction']);
        $this->assertSame('0.0000', $deducted['company_shipping_loss']);
        $this->assertSame('44.5000', $deducted['customer_refund_amount']);
    }

    public function test_cumulative_partial_refunds_assign_final_four_decimal_residue(): void
    {
        [$order, $payment, $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order, [
            'quantity' => '3.0000',
            'row_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
            'tax_amount' => '9.0000',
            'row_total' => '99.0000',
        ]);
        $first = app(RefundService::class)->quote($order, $this->input($item->id, '1.0000'));
        $this->persistQuote($order, $payment, $admin->id, $first, 1);
        $second = app(RefundService::class)->quote($order, $this->input($item->id, '1.0000'));
        $this->persistQuote($order, $payment, $admin->id, $second, 2);
        $third = app(RefundService::class)->quote($order, $this->input($item->id, '1.0000'));

        $this->assertSame('33.3333', $first['items'][0]['subtotal_amount']);
        $this->assertSame('33.3334', $second['items'][0]['subtotal_amount']);
        $this->assertSame('33.3333', $third['items'][0]['subtotal_amount']);
        $this->assertSame('3.3333', $third['items'][0]['discount_amount']);
        $this->assertSame('3.0000', $third['items'][0]['tax_amount']);
        $this->assertSame('33.0000', $third['items'][0]['line_amount']);
    }

    public function test_one_financial_item_definition_excludes_structural_zero_value_lines(): void
    {
        [$order] = $this->paidRefundOrder();
        $financial = $this->refundOrderItem($order);
        $this->refundOrderItem($order, [
            'sku' => 'STRUCTURAL',
            'row_subtotal' => '0.0000',
            'row_total' => '0.0000',
            'is_inventory_item' => false,
        ]);

        $items = app(RefundService::class)->refundableItems($order);
        $this->assertSame([$financial->id], $items->pluck('order_item.id')->all());
    }

    public function test_invalid_shipping_and_quantities_are_rejected_without_capping(): void
    {
        [$order] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);

        foreach ([
            ['quantity' => '0.0000', 'shipping' => '0.0000'],
            ['quantity' => '1.00001', 'shipping' => '0.0000'],
            ['quantity' => '1.0000', 'shipping' => '-1.0000'],
            ['quantity' => '1.0000', 'shipping' => '100000000000.0000'],
            ['quantity' => '1.0000', 'shipping' => '100.0000'],
        ] as $case) {
            try {
                app(RefundService::class)->quote($order, [
                    'items' => [['order_item_id' => $item->id, 'quantity' => $case['quantity']]],
                    'return_shipping_cost' => $case['shipping'],
                    'shipping_treatment' => ShippingTreatment::DeductFromRefund->value,
                ]);
                $this->fail('Invalid Refund input should fail.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    private function input(int $itemId, string $quantity): array
    {
        return [
            'items' => [['order_item_id' => $itemId, 'quantity' => $quantity]],
            'return_shipping_cost' => '0.0000',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
        ];
    }

    private function persistQuote($order, $payment, int $adminId, array $quote, int $number): void
    {
        $refund = Refund::query()->create([
            'refund_number' => sprintf('RFD-2026-%06d', $number),
            'idempotency_key' => str_repeat((string) $number, 64),
            'order_id' => $order->id,
            'order_payment_id' => $payment->id,
            'currency_code' => $order->currency_code,
            'merchandise_subtotal' => $quote['merchandise_subtotal'],
            'discount_amount' => $quote['discount_amount'],
            'tax_amount' => $quote['tax_amount'],
            'merchandise_amount' => $quote['merchandise_amount'],
            'return_shipping_cost' => $quote['return_shipping_cost'],
            'shipping_treatment' => $quote['shipping_treatment'],
            'shipping_deduction' => $quote['shipping_deduction'],
            'company_shipping_loss' => $quote['company_shipping_loss'],
            'customer_refund_amount' => $quote['customer_refund_amount'],
            'created_by' => $adminId,
            'refunded_at' => now(),
        ]);
        $refund->items()->createMany($quote['items']);
    }
}
