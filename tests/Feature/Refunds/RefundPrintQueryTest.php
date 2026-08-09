<?php

namespace Tests\Feature\Refunds;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class RefundPrintQueryTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_refund_print_queries_remain_bounded_as_items_and_options_grow(): void
    {
        [$firstRefund, $admin] = $this->refundWithRows(1);
        [$secondRefund] = $this->refundWithRows(12);
        foreach (['store_name', 'store_email', 'store_phone', 'store_address', 'store_logo_path'] as $key) {
            setting('store.'.$key, '');
        }

        $phase = 'first';
        $counts = ['first' => 0, 'second' => 0];
        $forbidden = 0;
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts, &$forbidden): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'products') || str_contains($sql, 'payment_attempts')) {
                $forbidden++;
            }
            if (in_array($phase, ['first', 'second'], true) && (
                str_contains($sql, 'refunds') || str_contains($sql, 'refund_items')
                || str_contains($sql, 'orders') || str_contains($sql, 'order_items')
                || str_contains($sql, 'order_item_options')
            )) {
                $counts[$phase]++;
            }
        });

        $this->actingAs($admin, 'admin')->get(route('admin.refunds.print', $firstRefund))->assertOk();
        $phase = 'second';
        $this->get(route('admin.refunds.print', $secondRefund))->assertOk();

        $this->assertSame($counts['first'], $counts['second']);
        $this->assertLessThanOrEqual(5, $counts['second']);
        $this->assertSame(0, $forbidden);
    }

    /** @return array{Refund, User} */
    private function refundWithRows(int $count): array
    {
        [$order, $payment, $admin] = $this->paidRefundOrder([
            'order_number' => 'ORD-PRINT-'.$count,
        ]);
        $refund = Refund::query()->create([
            'refund_number' => 'RFD-PRINT-'.$count,
            'idempotency_key' => hash('sha256', 'refund-print-'.$count),
            'order_id' => $order->id, 'order_payment_id' => $payment->id, 'currency_code' => 'USD',
            'merchandise_subtotal' => '100.0000', 'discount_amount' => '0.0000',
            'tax_amount' => '0.0000', 'merchandise_amount' => '100.0000',
            'return_shipping_cost' => '0.0000', 'shipping_treatment' => 'company_absorbs',
            'shipping_deduction' => '0.0000', 'company_shipping_loss' => '0.0000',
            'customer_refund_amount' => '100.0000', 'created_by' => $admin->id, 'refunded_at' => now(),
        ]);
        foreach (range(1, $count) as $index) {
            $item = $this->refundOrderItem($order, [
                'sku' => "QUERY-SKU-{$count}-{$index}", 'name' => "Query Item {$index}",
            ]);
            $item->options()->create([
                'attribute_code' => 'option', 'attribute_name' => 'Option',
                'option_code' => 'value-'.$index, 'option_label' => 'Value '.$index,
            ]);
            $refund->items()->create([
                'order_item_id' => $item->id, 'quantity' => '1.0000',
                'subtotal_amount' => '10.0000', 'discount_amount' => '0.0000',
                'tax_amount' => '0.0000', 'line_amount' => '10.0000',
            ]);
        }

        return [$refund, $admin];
    }
}
