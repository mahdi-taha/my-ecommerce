<?php

namespace Tests\Feature\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_without_note_and_rejection_requires_note(): void
    {
        $admin = User::factory()->create();
        $review = $this->review();
        $this->actingAs($admin, 'admin')->patch(route('admin.reviews.update', $review), ['status' => 'rejected'])->assertSessionHasErrors('admin_note');
        $this->actingAs($admin, 'admin')->patch(route('admin.reviews.update', $review), ['status' => 'approved'])->assertRedirect();
        $this->assertSame(ProductReviewStatus::Approved, $review->fresh()->status);
    }

    private function review(): ProductReview
    {
        return ProductReview::create(['product_id' => Product::factory()->create()->id, 'user_id' => User::factory()->customer()->create()->id, 'rating' => 5, 'review' => 'Moderate this review.', 'status' => ProductReviewStatus::Pending]);
    }
}
