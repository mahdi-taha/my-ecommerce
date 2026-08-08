<?php

namespace Tests\Feature\Storefront;

use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Refund;
use App\Services\StorefrontProductListingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopSellingProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_ranking_uses_net_financial_quantity_and_a_stable_product_tie(): void
    {
        $first = $this->product('First');
        $second = $this->product('Second');
        $unpaid = $this->product('Unpaid');
        $structural = $this->product('Structural');
        $fullyRefunded = $this->product('Fully Refunded');

        [$firstOrder, $firstItem] = $this->sale($first, 5, PaymentStatus::PartiallyRefunded);
        $this->refund($firstOrder, $firstItem, 2);
        $this->sale($second, 2, PaymentStatus::Paid);
        $this->sale($second, 1, PaymentStatus::Paid);
        $this->sale($unpaid, 20, PaymentStatus::Pending);
        $this->sale($structural, 20, PaymentStatus::Paid, rowTotal: 0);
        [$refundedOrder, $refundedItem] = $this->sale($fullyRefunded, 4, PaymentStatus::Refunded);
        $this->refund($refundedOrder, $refundedItem, 4);

        $products = app(StorefrontProductListingService::class)->topSellingPreview('en');

        $this->assertSame([$first->id, $second->id], $products->pluck('id')->all());
        $this->assertSame(['3', '3'], $products->pluck('net_units_sold')->map(fn ($value) => rtrim(rtrim((string) $value, '0'), '.'))->all());
    }

    public function test_variant_units_roll_up_only_to_an_eligible_configurable_parent(): void
    {
        [$eligibleParent, $eligibleVariant] = $this->configurable('Eligible Parent');
        [$inactiveParent, $inactiveVariant] = $this->configurable('Inactive Parent');
        $inactiveParent->update(['status' => false]);

        [$order, $item] = $this->sale($eligibleVariant, 6, PaymentStatus::PartiallyRefunded);
        $this->refund($order, $item, 1);
        $this->sale($inactiveVariant, 50, PaymentStatus::Paid);

        $products = app(StorefrontProductListingService::class)->topSellingPreview('en');

        $this->assertSame([$eligibleParent->id], $products->pluck('id')->all());
        $this->assertSame('5', rtrim(rtrim((string) $products->first()->net_units_sold, '0'), '.'));
        $this->assertNotContains($eligibleVariant->id, $products->pluck('id'));
        $this->assertNotContains($inactiveParent->id, $products->pluck('id'));
    }

    public function test_homepage_preview_is_limited_to_eight_and_never_falls_back(): void
    {
        foreach (range(1, 9) as $index) {
            $product = $this->product('Ranked '.$index);
            $this->sale($product, 20 - $index, PaymentStatus::Paid);
        }
        $this->product('No Sales');

        $response = $this->get(route('shop.home', ['locale' => 'en']))->assertOk();

        $response->assertSee(route('shop.products.index', ['locale' => 'en']), false)
            ->assertSee(route('shop.products.top-selling', ['locale' => 'en']), false)
            ->assertSee('Ranked 8')
            ->assertDontSee('Ranked 9')
            ->assertDontSee('No Sales');
    }

    public function test_dedicated_page_is_localized_filterable_fixed_order_and_empty_without_sales(): void
    {
        $camera = $this->product('Camera', ['is_featured' => true]);
        $phone = $this->product('Phone');
        $this->sale($camera, 2, PaymentStatus::Paid);
        $this->sale($phone, 8, PaymentStatus::Paid);

        $this->get(route('shop.products.top-selling', ['locale' => 'en', 'q' => 'Camera']))
            ->assertOk()
            ->assertSee('Camera')
            ->assertDontSee('Phone')
            ->assertDontSee('id="shop-sort"', false)
            ->assertSee('data-product-card-cart-form', false);
        $this->get(route('shop.products.top-selling', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('lang="ar"', false);
        $this->get(route('shop.products.top-selling', ['locale' => 'en', 'sort' => 'newest']))
            ->assertSessionHasErrors('sort');

        Order::query()->delete();
        $this->get(route('shop.products.top-selling', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('shop.listing.top_selling.empty'));
    }

    private function product(string $name, array $state = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 10,
        ], $state));
        $product->translations()->createMany([
            ['locale' => 'en', 'name' => $name, 'url_key' => str($name)->slug().'-en'],
            ['locale' => 'ar', 'name' => 'Arabic '.$name, 'url_key' => str($name)->slug().'-ar'],
        ]);
        $product->inventory()->create(['quantity' => 100, 'average_cost' => 1, 'low_stock_alert' => 1]);

        return $product;
    }

    /** @return array{Product, Product} */
    private function configurable(string $name): array
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $option = $attribute->options()->create(['code' => str($name)->slug(), 'sort_order' => 1]);
        $parent = $this->product($name, ['type' => ProductType::Configurable->value]);
        $parent->superAttributes()->create(['attribute_id' => $attribute->id])->options()->sync([$option->id]);
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'status' => true,
            'is_visible_individually' => false,
            'price' => 10,
        ]);
        $variant->attributeValues()->create([
            'attribute_id' => $attribute->id,
            'attribute_option_id' => $option->id,
        ]);
        $variant->inventory()->create(['quantity' => 100, 'average_cost' => 1, 'low_stock_alert' => 1]);

        return [$parent, $variant];
    }

    /** @return array{Order, OrderItem} */
    private function sale(
        Product $product,
        int $quantity,
        PaymentStatus $paymentStatus,
        int $rowTotal = 100,
    ): array {
        $order = Order::query()->create([
            'order_number' => 'ORD-'.fake()->unique()->numerify('########'),
            'customer_email' => 'buyer@example.test',
            'customer_first_name' => 'Storefront',
            'customer_last_name' => 'Buyer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'completed',
            'payment_status' => $paymentStatus->value,
            'fulfillment_status' => 'fulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => $rowTotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $rowTotal,
            'placed_at' => now(),
        ]);
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => $product->configurable_id ? 'variant' : 'simple',
            'sku' => 'SKU-'.fake()->unique()->numerify('########'),
            'name' => 'Sold Product',
            'quantity' => $quantity,
            'original_unit_price' => 10,
            'unit_price' => 10,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'row_subtotal' => $rowTotal,
            'discount_amount' => 0,
            'row_total' => $rowTotal,
            'is_inventory_item' => true,
        ]);

        return [$order, $item];
    }

    private function refund(Order $order, OrderItem $item, int $quantity): void
    {
        $payment = OrderPayment::query()->create([
            'payment_number' => 'PAY-'.fake()->unique()->numerify('########'),
            'order_id' => $order->id,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => $order->grand_total,
            'currency_code' => 'USD',
            'status' => $order->payment_status,
            'paid_amount' => $order->grand_total,
        ]);
        $refund = Refund::query()->create([
            'refund_number' => 'REF-'.fake()->unique()->numerify('########'),
            'idempotency_key' => hash('sha256', fake()->unique()->uuid()),
            'order_id' => $order->id,
            'order_payment_id' => $payment->id,
            'currency_code' => 'USD',
            'merchandise_subtotal' => 10 * $quantity,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'merchandise_amount' => 10 * $quantity,
            'return_shipping_cost' => 0,
            'shipping_treatment' => 'company_absorbs',
            'shipping_deduction' => 0,
            'company_shipping_loss' => 0,
            'customer_refund_amount' => 10 * $quantity,
            'refunded_at' => now(),
        ]);
        $refund->items()->create([
            'order_item_id' => $item->id,
            'quantity' => $quantity,
            'subtotal_amount' => 10 * $quantity,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_amount' => 10 * $quantity,
        ]);
    }
}
