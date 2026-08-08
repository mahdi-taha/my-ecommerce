<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderPrintQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_queries_remain_bounded_as_items_options_and_refunds_grow(): void
    {
        $admin = User::factory()->create();
        $first = $this->orderWithRows(1);
        $second = $this->orderWithRows(12);
        foreach (['store_name', 'store_email', 'store_phone', 'store_address', 'store_logo_path'] as $key) {
            setting('store.'.$key, '');
        }

        $phase = 'first';
        $counts = ['first' => 0, 'second' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            $sql = strtolower($query->sql);
            if (in_array($phase, ['first', 'second'], true) && (
                str_contains($sql, 'orders')
                || str_contains($sql, 'order_items')
                || str_contains($sql, 'order_item_options')
                || str_contains($sql, 'order_addresses')
                || str_contains($sql, 'order_shipping')
                || str_contains($sql, 'order_payments')
                || str_contains($sql, 'refunds')
            )) {
                $counts[$phase]++;
            }
        });

        $this->actingAs($admin, 'admin')->get(route('admin.orders.print', $first))->assertOk();
        $phase = 'second';
        $this->get(route('admin.orders.print', $second))->assertOk();

        $this->assertSame($counts['first'], $counts['second']);
        $this->assertLessThanOrEqual(9, $counts['second']);
    }

    private function orderWithRows(int $count): Order
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-QUERY-'.$count, 'customer_email' => 'query@example.test',
            'customer_first_name' => 'Query', 'customer_last_name' => 'Customer',
            'locale' => 'en', 'currency_code' => 'USD', 'status' => 'pending',
            'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery', 'requires_payment_before_processing' => false,
            'subtotal' => '10.0000', 'discount_total' => '0.0000', 'shipping_total' => '0.0000',
            'tax_total' => '0.0000', 'grand_total' => '10.0000', 'placed_at' => now(),
        ]);
        foreach (range(1, $count) as $index) {
            $item = $order->items()->create([
                'product_type' => 'simple', 'sku' => 'SKU-'.$count.'-'.$index,
                'name' => 'Item '.$index, 'quantity' => '1.0000',
                'original_unit_price' => '10.0000', 'unit_price' => '10.0000',
                'tax_rate' => '0.0000', 'tax_amount' => '0.0000',
                'row_subtotal' => '10.0000', 'discount_amount' => '0.0000',
                'row_total' => '10.0000', 'is_inventory_item' => true,
            ]);
            $item->options()->create([
                'attribute_code' => 'option', 'attribute_name' => 'Option',
                'option_code' => 'value-'.$index, 'option_label' => 'Value '.$index,
            ]);
        }

        return $order;
    }
}
