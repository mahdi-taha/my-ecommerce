<?php

namespace Tests\Feature\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductReviewMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_and_order_deletion_preserve_review_and_aggregates(): void
    {
        $this->assertTrue(Schema::hasColumns('product_reviews', ['product_id', 'user_id', 'order_item_id', 'rating', 'title', 'review', 'status', 'admin_note', 'reviewed_by', 'reviewed_at']));
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create();
        $order = $this->order($customer);
        $item = $order->items()->create($this->item($product));
        $review = ProductReview::create(['product_id' => $product->id, 'user_id' => $customer->id, 'order_item_id' => $item->id, 'rating' => 5, 'review' => 'Excellent product.', 'status' => ProductReviewStatus::Approved]);

        $order->delete();

        $this->assertNull($review->fresh()->order_item_id);
        $this->assertDatabaseHas('product_reviews', ['id' => $review->id]);
        $this->assertSame(1, $product->approvedReviews()->count());
        $this->assertSame('5.0000', number_format((float) $product->approvedReviews()->avg('rating'), 4, '.', ''));
    }

    private function order(User $user): Order
    {
        return Order::create(['order_number' => 'REV-1', 'user_id' => $user->id, 'customer_email' => $user->email, 'customer_first_name' => $user->first_name, 'customer_last_name' => $user->last_name, 'locale' => 'en', 'currency_code' => 'USD', 'status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'payment_method' => 'cod', 'subtotal' => 10, 'grand_total' => 10, 'placed_at' => now(), 'completed_at' => now()]);
    }

    private function item(Product $product): array
    {
        return ['product_id' => $product->id, 'product_type' => 'simple', 'sku' => $product->sku, 'name' => 'Product', 'quantity' => 1, 'original_unit_price' => 10, 'unit_price' => 10, 'tax_rate' => 0, 'tax_amount' => 0, 'row_subtotal' => 10, 'discount_amount' => 0, 'row_total' => 10, 'is_inventory_item' => true];
    }
}
