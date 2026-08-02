<?php

namespace Tests\Feature\Reviews;

use App\Enums\ProductReviewStatus;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductReviewAdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_uses_admin_card_and_datatable_contract(): void
    {
        $this->actingAs(User::factory()->create(), 'admin')->get(route('admin.reviews.index'))
            ->assertOk()->assertSee('card shadow mt-4', false)->assertSee('reviewsTable')
            ->assertSee('resources/js/admin/reviews.js');
    }

    public function test_datatable_returns_columns_status_filter_search_and_review_button(): void
    {
        $admin = User::factory()->create();
        $matching = $this->review('Special Camera', 'CAM-ONE', 'Alice Reviewer', 'alice@example.test', ProductReviewStatus::Pending, 'Detailed camera review.', now());
        $this->review('Other Product', 'OTHER', 'Bob Customer', 'bob@example.test', ProductReviewStatus::Approved, 'Unrelated content.', now()->subDay());

        $response = $this->actingAs($admin, 'admin')->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('admin.reviews.index', array_merge($this->parameters('camera'), ['status' => 'pending'])));

        $response->assertOk()->assertJsonPath('recordsFiltered', 1)
            ->assertJsonFragment(['product' => 'Special Camera', 'rating' => 5])
            ->assertJsonMissing(['product' => 'Other Product']);
        $action = collect($response->json('data'))->firstWhere('id', $matching->id)['action'];
        $this->assertStringContainsString(route('admin.reviews.show', $matching), $action);
        $this->assertStringContainsString('btn btn-sm btn-outline-primary', $action);
        $this->assertStringContainsString('>Review</a>', $action);
    }

    public function test_searches_customer_and_review_content_and_keeps_queries_bounded(): void
    {
        $admin = User::factory()->create();
        foreach (range(1, 6) as $index) {
            $this->review("Product {$index}", "SKU-{$index}", "Customer {$index}", "customer{$index}@example.test", ProductReviewStatus::Pending, "Unique body {$index}", now()->subMinutes($index));
        }
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin, 'admin')->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson(route('admin.reviews.index', $this->parameters('customer3@example.test')))
            ->assertOk()->assertJsonPath('recordsFiltered', 1);

        $this->assertLessThanOrEqual(8, count($queries));
    }

    public function test_details_page_renders_three_admin_cards(): void
    {
        $review = $this->review('Camera', 'CAM', 'Customer', 'customer@example.test', ProductReviewStatus::Pending, 'Review body content.', now());
        $this->actingAs(User::factory()->create(), 'admin')->get(route('admin.reviews.show', $review))
            ->assertOk()->assertSee('Review Details')->assertSee('Product, Customer, and Purchase Evidence')->assertSee('Moderation Actions');
    }

    private function review(string $name, string $sku, string $customerName, string $email, ProductReviewStatus $status, string $body, $createdAt): ProductReview
    {
        $product = Product::factory()->create(['sku' => $sku]);
        $product->translations()->create(['locale' => 'en', 'name' => $name, 'url_key' => strtolower($sku)]);
        $customer = User::factory()->customer()->create(['name' => $customerName, 'email' => $email]);

        return ProductReview::create(['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 5, 'title' => 'Review title', 'review' => $body, 'status' => $status, 'created_at' => $createdAt]);
    }

    private function parameters(string $search = ''): array
    {
        $names = ['product', 'customer', 'rating', 'status', 'created_at', 'action'];

        return ['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => collect($names)->map(fn (string $name) => ['data' => $name, 'name' => $name, 'searchable' => ! in_array($name, ['status', 'created_at', 'action'], true), 'orderable' => ! in_array($name, ['product', 'customer', 'action'], true), 'search' => ['value' => '', 'regex' => false]])->all(), 'order' => [['column' => 4, 'dir' => 'desc']], 'search' => ['value' => $search, 'regex' => false]];
    }
}
