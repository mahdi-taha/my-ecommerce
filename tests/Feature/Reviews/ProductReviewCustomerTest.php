<?php

namespace Tests\Feature\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_edit_review_after_purchase_evidence_was_deleted(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create();
        $review = ProductReview::create(['product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => null, 'rating' => 3, 'review' => 'Original review text.', 'status' => ProductReviewStatus::Approved, 'admin_note' => 'Approved']);
        $this->actingAs($customer, 'customer')->put(route('shop.account.reviews.update', $review), ['rating' => 4, 'title' => 'Updated', 'review' => 'Updated review text.'])->assertRedirect(route('shop.account.reviews.index'));
        $review->refresh();
        $this->assertSame(ProductReviewStatus::Pending, $review->status);
        $this->assertNull($review->order_item_id);
        $this->assertNull($review->admin_note);
    }

    public function test_another_customer_cannot_edit_review(): void
    {
        $review = ProductReview::create(['product_id' => Product::factory()->create()->id, 'user_id' => User::factory()->customer()->create()->id, 'rating' => 3, 'review' => 'Original review text.', 'status' => ProductReviewStatus::Pending]);
        $this->actingAs(User::factory()->customer()->create(), 'customer')->put(route('shop.account.reviews.update', $review), ['rating' => 4, 'review' => 'Updated review text.'])->assertNotFound();
    }
}
