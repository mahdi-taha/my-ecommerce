<?php

namespace Tests\Feature\Refunds;

use App\Enums\ShippingTreatment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ConcurrentProcessRunner;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class MySqlRefundConcurrencyTest extends TestCase
{
    use CreatesRefundOrders;

    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing')) {
            throw new RuntimeException('The concurrency suite may run only with APP_ENV=testing.');
        }
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('True Refund row-lock concurrency is verified only against MySQL.');
        }
        if (! preg_match('/test|testing/i', (string) DB::connection()->getDatabaseName())) {
            throw new RuntimeException('Refund concurrency requires a clearly named test database.');
        }
    }

    public function test_concurrent_refunds_cannot_exceed_remaining_merchandise_quantity(): void
    {
        [$order, , $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);
        $this->ids = ['order' => $order->id, 'admin' => $admin->id];
        $data = [
            'items' => [['order_item_id' => $item->id, 'quantity' => '1']],
            'return_shipping_cost' => '0',
            'shipping_treatment' => ShippingTreatment::CompanyAbsorbs->value,
        ];

        $results = (new ConcurrentProcessRunner(45))->run([
            ['action' => 'refund', 'payload' => ['order_id' => $order->id, 'admin_id' => $admin->id, 'data' => $data, 'idempotency_key' => str_repeat('4', 64)]],
            ['action' => 'refund', 'payload' => ['order_id' => $order->id, 'admin_id' => $admin->id, 'data' => $data, 'idempotency_key' => str_repeat('5', 64)]],
        ]);

        $this->assertSame(1, collect($results)->where('successful', true)->count());
        $this->assertSame(1, Refund::query()->where('order_id', $order->id)->count());
    }

    protected function tearDown(): void
    {
        if ($this->ids !== [] && DB::getDriverName() === 'mysql') {
            DB::table('database_notifications')->where('entity_type', 'refund')
                ->whereIn('entity_id', DB::table('refunds')->where('order_id', $this->ids['order'])->select('id'))->delete();
            DB::table('refund_items')->whereIn('refund_id', DB::table('refunds')->where('order_id', $this->ids['order'])->select('id'))->delete();
            DB::table('refunds')->where('order_id', $this->ids['order'])->delete();
            DB::table('order_status_history')->where('order_id', $this->ids['order'])->delete();
            DB::table('order_payments')->where('order_id', $this->ids['order'])->delete();
            DB::table('order_items')->where('order_id', $this->ids['order'])->delete();
            DB::table('orders')->where('id', $this->ids['order'])->delete();
            DB::table('users')->where('id', $this->ids['admin'])->delete();
        }
        parent::tearDown();
    }
}
