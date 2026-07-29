<?php

namespace Tests\Feature\Promotions;

use App\DTOs\Promotions\CouponUsageReleaseResult;
use App\Enums\CouponType;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponEligibilityService;
use App\Services\CouponUsageService;
use App\Services\OrderStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponUsageReleaseLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_cancellation_releases_usage_without_changing_snapshot(): void
    {
        [$order, $product, $usage] = $this->scenario();
        $snapshot = $usage->fresh()->getAttributes();

        app(OrderStatusService::class)->cancel($order);

        $this->assertDatabaseHas('coupon_usage_releases', [
            'coupon_usage_id' => $usage->id,
            'reason' => 'order_cancelled',
        ]);
        $this->assertSame($snapshot, $usage->fresh()->getAttributes());
        $this->assertSame('10.0000', $product->inventory()->firstOrFail()->quantity);
        $this->assertSame(PaymentStatus::Cancelled, $order->payment()->firstOrFail()->status);
        $this->assertEquals('10.0000', $order->fresh()->discount_total);
    }

    public function test_processing_cancellation_releases_once_and_preserves_inventory_restoration(): void
    {
        [$order, $product, $usage] = $this->scenario();
        $service = app(OrderStatusService::class);
        $service->process($order);
        $service->cancel($order);

        $this->assertSame('10.0000', $product->inventory()->firstOrFail()->quantity);
        $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovement::TYPE_RETURN)->count());
        $this->assertDatabaseCount('coupon_usage_releases', 1);
        $this->assertDatabaseHas('coupon_usage_releases', [
            'coupon_usage_id' => $usage->id,
            'reason' => 'order_cancelled',
        ]);

        try {
            $service->cancel($order);
            $this->fail('Duplicate cancellation was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('coupon_usage_releases', 1);
            $this->assertSame(1, InventoryMovement::query()->where('type', InventoryMovement::TYPE_RETURN)->count());
        }
    }

    public function test_delivery_failed_path_releases_usage_and_preserves_payment_behavior(): void
    {
        [$order, $product, $usage] = $this->scenario();
        $service = app(OrderStatusService::class);
        $service->process($order);
        $service->markOutForDelivery($order);
        $service->markDeliveryFailed($order);

        $this->assertSame(OrderStatus::Cancelled->value, $order->fresh()->status);
        $this->assertSame(FulfillmentStatus::DeliveryFailed->value, $order->fresh()->fulfillment_status);
        $this->assertSame(PaymentStatus::Cancelled->value, $order->fresh()->payment_status);
        $this->assertSame('10.0000', $product->inventory()->firstOrFail()->quantity);
        $this->assertDatabaseHas('coupon_usage_releases', [
            'coupon_usage_id' => $usage->id,
            'reason' => 'delivery_failed',
        ]);
    }

    public function test_orders_without_usage_and_completed_orders_do_not_release(): void
    {
        [$order] = $this->scenario(withUsage: false);
        app(OrderStatusService::class)->cancel($order);
        $this->assertDatabaseCount('coupon_usage_releases', 0);

        [$completed, , $usage] = $this->scenario();
        $completed->update(['status' => OrderStatus::Completed->value]);
        $result = DB::transaction(
            fn () => app(CouponUsageService::class)->release($completed, 'order_cancelled')
        );

        $this->assertSame(CouponUsageReleaseResult::NOT_APPLICABLE, $result->outcome);
        $this->assertNull($result->release);
        $this->assertDatabaseMissing('coupon_usage_releases', ['coupon_usage_id' => $usage->id]);
    }

    public function test_release_is_idempotent_and_database_unique_constraint_remains_authoritative(): void
    {
        [$order, , $usage] = $this->scenario();
        [$first, $second] = DB::transaction(function () use ($order): array {
            $service = app(CouponUsageService::class);

            return [
                $service->release($order, 'order_cancelled'),
                $service->release($order, 'order_cancelled'),
            ];
        });

        $this->assertSame(CouponUsageReleaseResult::RELEASED, $first->outcome);
        $this->assertSame(CouponUsageReleaseResult::ALREADY_RELEASED, $second->outcome);
        $this->assertTrue($first->release->is($second->release));
        $this->assertDatabaseCount('coupon_usage_releases', 1);

        try {
            DB::table('coupon_usage_releases')->insert([
                'coupon_usage_id' => $usage->id,
                'reason' => 'order_cancelled',
                'released_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('The database accepted a duplicate Coupon usage release.');
        } catch (QueryException) {
            $this->assertDatabaseCount('coupon_usage_releases', 1);
        }
    }

    public function test_release_rolls_back_when_later_cancellation_persistence_fails(): void
    {
        [$order, $product, $usage] = $this->scenario();
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER reject_cancelled_order
            BEFORE UPDATE ON orders
            FOR EACH ROW
            WHEN NEW.status = 'cancelled'
            BEGIN
                SELECT RAISE(ABORT, 'forced cancellation failure');
            END;
        SQL);

        try {
            app(OrderStatusService::class)->cancel($order);
            $this->fail('The forced cancellation failure did not roll back.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('coupon_usage_releases', ['coupon_usage_id' => $usage->id]);
            $this->assertSame(OrderStatus::Pending->value, $order->fresh()->status);
            $this->assertSame(PaymentStatus::Pending->value, $order->fresh()->payment_status);
            $this->assertSame(PaymentStatus::Pending, $order->payment()->firstOrFail()->status);
            $this->assertSame('10.0000', $product->inventory()->firstOrFail()->quantity);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS reject_cancelled_order');
        }
    }

    public function test_release_restores_global_customer_and_first_order_eligibility(): void
    {
        $customer = User::factory()->customer()->create();
        [$order, , $usage, $coupon] = $this->scenario($customer, true, [
            'usage_limit' => 1,
            'per_customer_usage_limit' => 1,
            'is_first_order_only' => true,
        ]);
        $eligibility = app(CouponEligibilityService::class);

        $this->assertContains('coupon_usage_limit_reached', $eligibility->validate($coupon, '100.0000', $customer));
        $this->assertContains('coupon_customer_limit_reached', $eligibility->validate($coupon, '100.0000', $customer));
        $this->assertContains('coupon_first_order_ineligible', $eligibility->validate($coupon, '100.0000', $customer));

        app(OrderStatusService::class)->cancel($order);

        $this->assertSame([], $eligibility->validate($coupon, '100.0000', $customer));
        $this->assertDatabaseHas('coupon_usage_releases', ['coupon_usage_id' => $usage->id]);
    }

    private function scenario(
        ?User $customer = null,
        bool $withUsage = true,
        array $couponState = []
    ): array {
        $product = Product::factory()->create([
            'type' => 'simple',
            'status' => true,
            'is_visible_individually' => true,
            'price' => '100.0000',
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000',
            'average_cost' => '20.0000',
            'low_stock_alert' => '1.0000',
        ]);
        $order = Order::create([
            'order_number' => 'ORD-RELEASE-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'guest@example.com',
            'customer_first_name' => 'Coupon',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => OrderStatus::Pending->value,
            'payment_status' => PaymentStatus::Pending->value,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
            'payment_method' => 'cash_on_delivery',
            'subtotal' => '100.0000',
            'discount_total' => $withUsage ? '10.0000' : '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => $withUsage ? '90.0000' : '100.0000',
            'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => 'simple',
            'sku' => $product->sku,
            'name' => 'Coupon Product',
            'quantity' => '1.0000',
            'original_unit_price' => '100.0000',
            'unit_price' => '100.0000',
            'discount_amount' => $withUsage ? '10.0000' : '0.0000',
            'tax_amount' => '0.0000',
            'row_subtotal' => '100.0000',
            'row_total' => $withUsage ? '90.0000' : '100.0000',
            'unit_cost' => null,
            'is_inventory_item' => true,
        ]);
        OrderPayment::create([
            'payment_number' => 'PAY-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => $order->grand_total,
            'currency_code' => 'USD',
            'status' => PaymentStatus::Pending,
            'paid_amount' => '0.0000',
        ]);
        $coupon = Coupon::factory()->create(array_merge([
            'is_active' => true,
            'type' => CouponType::Fixed,
            'value' => '10.0000',
        ], $couponState));
        $usage = $withUsage ? CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $customer?->id,
            'coupon_code' => $coupon->code,
            'coupon_type' => $coupon->type,
            'coupon_value' => $coupon->value,
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]) : null;

        return [$order, $product, $usage, $coupon];
    }
}
