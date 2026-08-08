<?php

namespace Tests\Feature\Refunds;

use App\Enums\AccountType;
use App\Enums\ShippingTreatment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class RefundMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_schema_supports_immutable_four_decimal_aggregates(): void
    {
        $this->assertTrue(Schema::hasColumns('refunds', [
            'refund_number', 'idempotency_key', 'order_id', 'order_payment_id',
            'merchandise_subtotal', 'discount_amount', 'tax_amount', 'merchandise_amount',
            'return_shipping_cost', 'shipping_treatment', 'shipping_deduction',
            'company_shipping_loss', 'customer_refund_amount', 'created_by', 'refunded_at',
        ]));
        $this->assertTrue(Schema::hasColumns('refund_items', [
            'refund_id', 'order_item_id', 'quantity', 'subtotal_amount',
            'discount_amount', 'tax_amount', 'line_amount',
        ]));
        $this->assertDatabaseHas('document_sequences', [
            'document_type' => 'refund',
            'last_number' => 0,
        ]);

        [$order, $payment, $item, $admin] = $this->aggregate();
        $refund = $this->refund($order, $payment, $admin, 'a');
        $refundItem = $refund->items()->create([
            'order_item_id' => $item->id,
            'quantity' => '0.5000',
            'subtotal_amount' => '5.0000',
            'discount_amount' => '1.0000',
            'tax_amount' => '0.4000',
            'line_amount' => '4.4000',
        ]);

        $this->assertSame('2.5000', $refund->return_shipping_cost);
        $this->assertSame(ShippingTreatment::CompanyAbsorbs, $refund->shipping_treatment);
        $this->assertSame('0.5000', $refundItem->quantity);
        $this->assertSame($refund->id, $order->refunds()->firstOrFail()->id);
        $this->assertSame($refundItem->id, $item->refundItems()->firstOrFail()->id);
    }

    public function test_refunds_are_append_only_and_identity_fields_are_unique(): void
    {
        [$order, $payment, $item, $admin] = $this->aggregate();
        $refund = $this->refund($order, $payment, $admin, 'b');
        $refundItem = $refund->items()->create([
            'order_item_id' => $item->id,
            'quantity' => '1.0000',
            'subtotal_amount' => '10.0000',
            'discount_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'line_amount' => '10.0000',
        ]);

        try {
            $refund->update(['reason' => 'changed']);
            $this->fail('Refund update should fail.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        try {
            $refundItem->delete();
            $this->fail('Refund item delete should fail.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $this->refund($order, $payment, $admin, 'b');
    }

    private function refund(Order $order, OrderPayment $payment, User $admin, string $key): Refund
    {
        return Refund::query()->create([
            'refund_number' => 'RFD-2026-00000'.($key === 'a' ? '1' : '2'),
            'idempotency_key' => str_repeat($key, 64),
            'order_id' => $order->id,
            'order_payment_id' => $payment->id,
            'currency_code' => 'USD',
            'merchandise_subtotal' => '10.0000',
            'discount_amount' => '0.0000',
            'tax_amount' => '0.0000',
            'merchandise_amount' => '10.0000',
            'return_shipping_cost' => '2.5000',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs,
            'shipping_deduction' => '0.0000',
            'company_shipping_loss' => '2.5000',
            'customer_refund_amount' => '10.0000',
            'created_by' => $admin->id,
            'refunded_at' => now(),
        ]);
    }

    /** @return array{Order, OrderPayment, OrderItem, User} */
    private function aggregate(): array
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        $order = Order::query()->create([
            'order_number' => 'ORD-2026-000001',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);
        $payment = OrderPayment::query()->create([
            'payment_number' => 'PAY-2026-000001',
            'order_id' => $order->id,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => 'paid',
            'paid_amount' => '10.0000',
            'paid_at' => now(),
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_type' => 'simple',
            'sku' => 'SKU-1',
            'name' => 'Item',
            'quantity' => '1.0000',
            'original_unit_price' => '10.0000',
            'unit_price' => '10.0000',
            'tax_amount' => '0.0000',
            'row_subtotal' => '10.0000',
            'discount_amount' => '0.0000',
            'row_total' => '10.0000',
            'is_inventory_item' => true,
        ]);

        return [$order, $payment, $item, $admin];
    }
}
