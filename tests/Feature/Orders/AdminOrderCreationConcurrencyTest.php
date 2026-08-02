<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminOrderCreationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creation_key_is_nullable_and_database_unique(): void
    {
        $this->assertTrue(Schema::hasColumn('orders', 'admin_creation_key'));
        $this->assertTrue(collect(Schema::getIndexes('orders'))->contains(
            fn (array $index): bool => $index['unique']
                && $index['columns'] === ['admin_creation_key']
        ));

        DB::table('orders')->insert([
            $this->orderRow('ORD-2026-900001', hash('sha256', 'one')),
            $this->orderRow('ORD-2026-900002', null),
            $this->orderRow('ORD-2026-900003', null),
        ]);
        $this->expectException(QueryException::class);
        DB::table('orders')->insert($this->orderRow(
            'ORD-2026-900004',
            hash('sha256', 'one')
        ));
    }

    public function test_admin_creation_key_is_immutable_after_creation(): void
    {
        DB::table('orders')->insert($this->orderRow(
            'ORD-2026-900001',
            hash('sha256', 'one')
        ));
        $order = Order::query()->where('order_number', 'ORD-2026-900001')->firstOrFail();

        $this->expectException(\LogicException::class);
        $order->update(['admin_creation_key' => hash('sha256', 'two')]);
    }

    private function orderRow(string $number, ?string $key): array
    {
        return [
            'order_number' => $number,
            'admin_creation_key' => $key,
            'customer_email' => null,
            'customer_first_name' => 'Concurrency',
            'customer_last_name' => 'Test',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '1.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '1.0000',
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
