<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductReview;
use App\Models\User;
use App\Services\Reports\InventoryReportQuery;
use App\Services\Reports\ReviewsReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_uses_current_production_semantics(): void
    {
        $product = Product::factory()->create();
        ProductInventory::query()->create(['product_id' => $product->id, 'quantity' => '5', 'average_cost' => '2', 'low_stock_alert' => '5']);
        $row = app(InventoryReportQuery::class)->rows($this->filters())->items()[0];
        $this->assertEquals(5, $row->on_hand);
        $this->assertEquals(5, $row->available);
        $this->assertEquals(0, $row->reserved);
        $this->assertEquals(10, $row->valuation);
    }

    public function test_review_average_includes_only_approved_reviews(): void
    {
        $product = Product::factory()->create();
        foreach ([['approved', 5], ['pending', 1]] as [$status, $rating]) {
            ProductReview::query()->create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'status' => $status, 'rating' => $rating, 'review' => 'Review']);
        }
        $row = app(ReviewsReportQuery::class)->rows($this->filters())->items()[0];
        $this->assertEquals(5, $row->average_rating);
    }

    private function filters(): ReportFilters
    {
        return new ReportFilters(null, null, null, null, null, null, null, null, null, null, null, 25);
    }
}
