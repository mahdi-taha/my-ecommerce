<?php

namespace Tests\Feature\Storefront;

use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StorefrontProductListingService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TopSellingProductsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_queries_remain_bounded_as_ranked_catalog_grows(): void
    {
        $this->soldProduct('Initial', 1);
        $service = app(StorefrontProductListingService::class);
        $service->paginateTopSelling([], 'en');
        $phase = 'small';
        $counts = ['small' => 0, 'large' => 0];
        DB::listen(function (QueryExecuted $query) use (&$phase, &$counts): void {
            if (isset($counts[$phase])) {
                $counts[$phase]++;
            }
        });

        $service->paginateTopSelling([], 'en');
        $phase = 'setup';
        foreach (range(1, 20) as $index) {
            $this->soldProduct('Bulk '.$index, $index + 1);
        }
        $phase = 'large';
        $service->paginateTopSelling([], 'en');

        $this->assertSame($counts['small'], $counts['large']);
        $this->assertLessThanOrEqual(15, $counts['large']);
    }

    private function soldProduct(string $name, int $quantity): void
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 10,
        ]);
        $product->translations()->create(['locale' => 'en', 'name' => $name, 'url_key' => str($name)->slug()]);
        $product->inventory()->create(['quantity' => 100, 'average_cost' => 1, 'low_stock_alert' => 1]);
        $order = Order::query()->create([
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'customer_email' => 'buyer@example.test',
            'customer_first_name' => 'Query',
            'customer_last_name' => 'Buyer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'completed',
            'payment_status' => PaymentStatus::Paid->value,
            'fulfillment_status' => 'fulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
            'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => 'simple',
            'sku' => 'SKU-'.fake()->unique()->numerify('########'),
            'name' => $name,
            'quantity' => $quantity,
            'original_unit_price' => 10,
            'unit_price' => 10,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'row_subtotal' => 10,
            'discount_amount' => 0,
            'row_total' => 10,
            'is_inventory_item' => true,
        ]);
    }
}
