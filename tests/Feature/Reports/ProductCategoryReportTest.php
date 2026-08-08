<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reports\CategoriesReportQuery;
use App\Services\Reports\ProductsReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class ProductCategoryReportTest extends TestCase
{
    use CreatesRefundOrders, RefreshDatabase;

    public function test_product_report_uses_order_item_snapshots(): void
    {
        [$order] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order, ['sku' => 'SNAP-SKU', 'name' => 'Snapshot Name', 'quantity' => '2']);
        $row = app(ProductsReportQuery::class)->rows($this->filters())->items()[0];
        $this->assertSame('SNAP-SKU', $row->sku);
        $this->assertSame('Snapshot Name', $row->name);
        $this->assertEquals(2, $row->units_sold);
    }

    public function test_category_report_uses_current_membership_and_descendants(): void
    {
        [$order] = $this->paidRefundOrder();
        $product = Product::factory()->create();
        $this->refundOrderItem($order, ['product_id' => $product->id]);
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id, 'level' => 1]);
        $product->categories()->attach($child);
        $filters = $this->filters();
        $filters = new ReportFilters($filters->start, $filters->endExclusive, null, null, $parent->id, null, null, null, null, null, null, null, 25);
        $this->assertSame(1, app(CategoriesReportQuery::class)->rows($filters)->total());
    }

    private function filters(): ReportFilters
    {
        return new ReportFilters(null, null, null, null, null, null, null, null, null, null, null, null, 25);
    }
}
