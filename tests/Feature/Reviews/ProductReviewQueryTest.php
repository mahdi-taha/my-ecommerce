<?php

namespace Tests\Feature\Reviews;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductReviewQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_review_aggregates_are_loaded_without_per_product_queries(): void
    {
        Product::factory()->count(3)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        Product::query()->withStorefrontCardData('en')->get();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($query) => str_contains($query, 'product_reviews'));
        $this->assertLessThanOrEqual(2, $queries->count());
    }
}
