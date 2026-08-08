<?php

namespace Tests\Support;

use App\Enums\AccountType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\User;

trait CreatesRefundOrders
{
    /** @return array{Order, OrderPayment, User} */
    protected function paidRefundOrder(array $orderOverrides = []): array
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::Admin,
            'is_active' => true,
        ]);
        $defaults = [
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'customer_first_name' => 'Refund',
            'customer_last_name' => 'Customer',
            'customer_email' => 'refund@example.test',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'completed',
            'payment_status' => PaymentStatus::Paid->value,
            'fulfillment_status' => 'fulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '100.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '100.0000',
            'placed_at' => now(),
            'paid_at' => now(),
        ];
        $order = Order::query()->create(array_merge($defaults, $orderOverrides));
        $payment = OrderPayment::query()->create([
            'payment_number' => 'PAY-2026-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => $order->grand_total,
            'currency_code' => $order->currency_code,
            'status' => $order->payment_status,
            'paid_amount' => $order->grand_total,
            'paid_at' => $order->paid_at,
        ]);

        return [$order, $payment, $admin];
    }

    protected function refundOrderItem(Order $order, array $overrides = []): OrderItem
    {
        return OrderItem::query()->create(array_merge([
            'order_id' => $order->id,
            'product_type' => 'simple',
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'name' => 'Refundable Item',
            'quantity' => '1.0000',
            'original_unit_price' => '100.0000',
            'unit_price' => '100.0000',
            'tax_name' => null,
            'tax_rate' => '0.0000',
            'tax_amount' => '0.0000',
            'row_subtotal' => '100.0000',
            'discount_amount' => '0.0000',
            'row_total' => '100.0000',
            'is_inventory_item' => true,
        ], $overrides));
    }
}
