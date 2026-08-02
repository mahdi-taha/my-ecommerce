<?php

namespace Tests\Feature\Reviews;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductReviewEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_newest_completed_purchase_is_selected_deterministically_and_duplicates_are_rejected(): void
    {
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create();
        $old = $this->item($customer, $product, now()->subDay(), 'REV-OLD');
        $new = $this->item($customer, $product, now(), 'REV-NEW');
        $service = app(ProductReviewService::class);
        $review = $service->create($customer, $product, ['rating' => 5, 'review' => 'A verified review.']);
        $this->assertSame($new->id, $review->order_item_id);
        $this->assertNotSame($old->id, $review->order_item_id);
        $this->expectException(ValidationException::class);
        $service->create($customer, $product, ['rating' => 4, 'review' => 'Another review.']);
    }

    public function test_current_completed_purchase_is_required_for_initial_submission(): void
    {
        $this->expectException(ValidationException::class);
        app(ProductReviewService::class)->create(User::factory()->customer()->create(), Product::factory()->create(), ['rating' => 5, 'review' => 'No purchase exists.']);
    }

    private function item(User $customer, Product $product, $placedAt, string $number)
    {
        $order = Order::create(['order_number' => $number, 'user_id' => $customer->id, 'customer_email' => $customer->email, 'customer_first_name' => $customer->first_name, 'customer_last_name' => $customer->last_name, 'locale' => 'en', 'currency_code' => 'USD', 'status' => 'completed', 'payment_status' => 'paid', 'fulfillment_status' => 'fulfilled', 'payment_method' => 'cod', 'subtotal' => 10, 'grand_total' => 10, 'placed_at' => $placedAt, 'completed_at' => $placedAt]);

        return $order->items()->create(['product_id' => $product->id, 'product_type' => 'simple', 'sku' => $product->sku, 'name' => 'Product', 'quantity' => 1, 'original_unit_price' => 10, 'unit_price' => 10, 'tax_rate' => 0, 'tax_amount' => 0, 'row_subtotal' => 10, 'discount_amount' => 0, 'row_total' => 10, 'is_inventory_item' => true]);
    }
}
