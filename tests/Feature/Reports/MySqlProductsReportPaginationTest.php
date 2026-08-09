<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Models\Product;
use App\Services\Reports\ProductsReportQuery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class MySqlProductsReportPaginationTest extends TestCase
{
    use CreatesRefundOrders;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing')) {
            throw new RuntimeException('The MySQL Reports suite may run only with APP_ENV=testing.');
        }

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Calculated-alias pagination is verified against MySQL.');
        }

        if (! preg_match('/test|testing/i', (string) DB::connection()->getDatabaseName())) {
            throw new RuntimeException('Reports pagination requires a clearly named MySQL test database.');
        }

        foreach (['orders', 'order_items', 'products'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("The migrated MySQL test table [{$table}] is required.");
            }
        }
    }

    public function test_products_report_counts_multiple_pages_without_ordering_by_a_removed_alias(): void
    {
        [$order] = $this->paidRefundOrder();
        foreach (range(1, 30) as $index) {
            $product = Product::factory()->create(['sku' => sprintf('MYSQL-REPORT-%02d', $index)]);
            $this->refundOrderItem($order, [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => "MySQL Report Product {$index}",
                'quantity' => sprintf('%d.0000', 31 - $index),
            ]);
        }

        $rows = app(ProductsReportQuery::class)->rows($this->filters());

        $this->assertSame(30, $rows->total());
        $this->assertSame(2, $rows->lastPage());
        $this->assertSame('MYSQL-REPORT-01', $rows->items()[0]->sku);
        $this->assertEquals(30, $rows->items()[0]->units_sold);
    }

    private function filters(): ReportFilters
    {
        return new ReportFilters(null, null, null, null, null, null, null, null, null, null, null, 25);
    }
}
