<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderIndexPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_listing_indexes_match_filter_and_sort_shapes(): void
    {
        $indexes = collect(Schema::getIndexes('orders'))->keyBy('name');

        $this->assertSame(
            ['user_id', 'placed_at', 'id'],
            $indexes->get('orders_user_id_placed_at_id_index')['columns']
        );
        $this->assertSame(
            ['payment_status', 'placed_at'],
            $indexes->get('orders_payment_status_placed_at_index')['columns']
        );
        $this->assertSame(
            ['fulfillment_status', 'placed_at'],
            $indexes->get('orders_fulfillment_status_placed_at_index')['columns']
        );
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertTrue($indexes->has('orders_user_id_foreign'));
        }
        $this->assertTrue($indexes->has('orders_placed_at_index'));
        $this->assertTrue($indexes->has('orders_status_placed_at_index'));
        $this->assertFalse($indexes->has('orders_payment_status_index'));
        $this->assertFalse($indexes->has('orders_fulfillment_status_index'));
    }

    public function test_admin_date_filters_use_utc_timestamp_boundaries_without_user_eager_loading(): void
    {
        Setting::query()->updateOrCreate(
            ['group' => 'localization', 'key' => 'timezone'],
            ['value' => 'Asia/Beirut', 'type' => 'text']
        );
        cache()->forget('setting.localization.timezone');

        $admin = User::factory()->create();
        $includedStart = $this->order('ORD-INDEX-START', CarbonImmutable::parse('2026-07-31 21:00:00', 'UTC'));
        $includedEnd = $this->order('ORD-INDEX-END', CarbonImmutable::parse('2026-08-01 20:59:59', 'UTC'));
        $excludedBefore = $this->order('ORD-INDEX-BEFORE', CarbonImmutable::parse('2026-07-31 20:59:59', 'UTC'));
        $excludedAfter = $this->order('ORD-INDEX-AFTER', CarbonImmutable::parse('2026-08-01 21:00:00', 'UTC'));
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $response = $this->actingAs($admin, 'admin')
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('admin.orders.index', array_merge($this->dataTableParameters(), [
                'date_from' => '2026-08-01',
                'date_to' => '2026-08-01',
            ])));

        $response->assertOk()
            ->assertJsonFragment(['order_number' => $includedStart->order_number])
            ->assertJsonFragment(['order_number' => $includedEnd->order_number])
            ->assertJsonMissing(['order_number' => $excludedBefore->order_number])
            ->assertJsonMissing(['order_number' => $excludedAfter->order_number]);

        $orderQueries = collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'from "orders"')
            || str_contains($sql, 'from `orders`'));

        $this->assertTrue($orderQueries->contains(
            fn (string $sql): bool => preg_match('/[`"]placed_at[`"]\s*>=\s*\?/', $sql) === 1
                && preg_match('/[`"]placed_at[`"]\s*<\s*\?/', $sql) === 1
                && ! str_contains($sql, 'date(')
        ));
        $this->assertFalse(collect($queries)->contains(
            fn (string $sql): bool => preg_match('/from\s+[`"]users[`"].*where\s+[`"]users[`"]\.[`"]id[`"]\s+in/i', $sql) === 1
        ));
    }

    public function test_mysql_explain_confirms_composite_indexes_satisfy_listing_order(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL or MariaDB is required for optimizer-plan assertions.');
        }

        $plans = [
            'orders_user_id_placed_at_id_index' => 'SELECT id FROM orders FORCE INDEX (orders_user_id_placed_at_id_index) WHERE user_id = 1 ORDER BY placed_at DESC, id DESC LIMIT 10',
            'orders_payment_status_placed_at_index' => "SELECT id FROM orders FORCE INDEX (orders_payment_status_placed_at_index) WHERE payment_status = 'pending' ORDER BY placed_at DESC LIMIT 10",
            'orders_fulfillment_status_placed_at_index' => "SELECT id FROM orders FORCE INDEX (orders_fulfillment_status_placed_at_index) WHERE fulfillment_status = 'unfulfilled' ORDER BY placed_at DESC LIMIT 10",
            'orders_status_placed_at_index' => "SELECT id FROM orders FORCE INDEX (orders_status_placed_at_index) WHERE status = 'pending' ORDER BY placed_at DESC LIMIT 10",
        ];

        foreach ($plans as $expectedIndex => $sql) {
            $explain = DB::selectOne('EXPLAIN '.$sql);

            $this->assertSame($expectedIndex, $explain->key);
            $this->assertStringNotContainsString('Using filesort', $explain->Extra ?? '');
            $this->assertStringNotContainsString('Using temporary', $explain->Extra ?? '');
        }
    }

    private function order(string $number, CarbonImmutable $placedAt): Order
    {
        return Order::query()->create([
            'order_number' => $number,
            'customer_email' => 'index-review@example.test',
            'customer_first_name' => 'Index',
            'customer_last_name' => 'Review',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => $placedAt,
        ]);
    }

    /** @return array<string, mixed> */
    private function dataTableParameters(): array
    {
        $columns = collect([
            'order_number',
            'customer',
            'placed_at',
            'items_count',
            'grand_total',
            'status',
            'payment_status',
            'fulfillment_status',
            'action',
        ])->map(fn (string $column): array => [
            'data' => $column,
            'name' => $column,
            'searchable' => ! in_array($column, ['items_count', 'grand_total', 'status', 'payment_status', 'fulfillment_status', 'action'], true),
            'orderable' => ! in_array($column, ['customer', 'action'], true),
            'search' => ['value' => '', 'regex' => false],
        ])->all();

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => $columns,
            'order' => [['column' => 2, 'dir' => 'desc']],
            'search' => ['value' => '', 'regex' => false],
        ];
    }
}
