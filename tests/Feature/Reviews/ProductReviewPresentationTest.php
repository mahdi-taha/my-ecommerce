<?php

namespace Tests\Feature\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_reviews_contribute_to_card_aggregates(): void
    {
        $product = Product::factory()->create();
        foreach ([[5, ProductReviewStatus::Approved], [1, ProductReviewStatus::Pending], [2, ProductReviewStatus::Rejected]] as [$rating, $status]) {
            ProductReview::create(['product_id' => $product->id, 'user_id' => User::factory()->customer()->create()->id, 'rating' => $rating, 'review' => 'A review body.', 'status' => $status]);
        }
        $loaded = Product::query()->withStorefrontCardData('en')->findOrFail($product->id);
        $this->assertSame(1, $loaded->approved_reviews_count);
        $this->assertSame(5.0, (float) $loaded->approved_reviews_avg_rating);
    }
}
