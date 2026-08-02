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

    public function test_rejection_error_is_visible_and_valid_rejection_persists_note(): void
    {
        $admin = User::factory()->create();
        $review = $this->review();

        $this->actingAs($admin, 'admin')->from(route('admin.reviews.show', $review))
            ->patch(route('admin.reviews.update', $review), ['status' => 'rejected'])
            ->assertRedirect(route('admin.reviews.show', $review))->assertSessionHasErrors('admin_note');
        $this->actingAs($admin, 'admin')->get(route('admin.reviews.show', $review))
            ->assertSee('is-invalid', false)->assertSee('invalid-feedback', false)
            ->assertSee('The admin note field is required.');

        $this->actingAs($admin, 'admin')->patch(route('admin.reviews.update', $review), ['status' => 'rejected', 'admin_note' => 'Insufficient detail.'])->assertRedirect();
        $review->refresh();
        $this->assertSame(ProductReviewStatus::Rejected, $review->status);
        $this->assertSame('Insufficient detail.', $review->admin_note);
    }

    public function test_entered_note_is_preserved_after_validation_failure(): void
    {
        $admin = User::factory()->create();
        $review = $this->review();
        $this->actingAs($admin, 'admin')->from(route('admin.reviews.show', $review))
            ->patch(route('admin.reviews.update', $review), ['status' => 'invalid', 'admin_note' => 'Keep this note.'])
            ->assertSessionHasErrors('status');
        $this->actingAs($admin, 'admin')->get(route('admin.reviews.show', $review))->assertSee('Keep this note.');
    }

    private function review(): ProductReview
    {
        return ProductReview::create(['product_id' => Product::factory()->create()->id, 'user_id' => User::factory()->customer()->create()->id, 'rating' => 5, 'review' => 'Moderate this review.', 'status' => ProductReviewStatus::Pending]);
    }
}
