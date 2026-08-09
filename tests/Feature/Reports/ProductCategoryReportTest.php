<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Models\Category;
use App\Models\Product;
use App\Services\Reports\CategoriesReportQuery;
use App\Services\Reports\ProductsReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
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
        $filters = new ReportFilters($filters->start, $filters->endExclusive, null, $parent->id, null, null, null, null, null, null, null, 25);
        $rows = app(CategoriesReportQuery::class)->rows($filters);
        $this->assertSame(1, $rows->total());
        $this->assertSame($parent->id, $rows->items()[0]->id);
    }

    public function test_product_report_paginates_calculated_units_with_deterministic_ordering(): void
    {
        [$order] = $this->paidRefundOrder();
        foreach (range(1, 27) as $index) {
            $product = Product::factory()->create(['sku' => sprintf('PAGED-%02d', $index)]);
            $this->refundOrderItem($order, [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => "Paged Product {$index}",
                'quantity' => $index <= 2 ? '50.0000' : sprintf('%d.0000', 30 - $index),
            ]);
        }

        $firstPage = app(ProductsReportQuery::class)->rows($this->filters());
        $this->assertSame(27, $firstPage->total());
        $this->assertSame(2, $firstPage->lastPage());
        $this->assertSame(['PAGED-01', 'PAGED-02'], collect($firstPage->items())->take(2)->pluck('sku')->all());

        Paginator::currentPageResolver(fn () => 2);
        try {
            $secondPage = app(ProductsReportQuery::class)->rows($this->filters());
            $this->assertCount(2, $secondPage->items());
        } finally {
            Paginator::currentPageResolver(fn () => 1);
        }
    }

    private function filters(): ReportFilters
    {
        return new ReportFilters(null, null, null, null, null, null, null, null, null, null, null, 25);
    }
}
