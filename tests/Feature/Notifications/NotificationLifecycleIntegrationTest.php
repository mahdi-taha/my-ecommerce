<?php

namespace Tests\Feature\Notifications;

use App\Enums\CartItemType;
use App\Enums\NotificationEventCode;
use App\Enums\ProductType;
use App\Events\CommerceEventOccurred;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use App\Services\OrderCancellationRequestService;
use App\Services\OrderStatusService;
use App\Services\PaymentStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_dispatches_order_placed_once_after_success(): void
    {
        Event::fake([CommerceEventOccurred::class]);
        [$cart, $customer, $shipping, $payment] = $this->checkoutScenario();

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertTrue($result->successful);
        $this->assertEvent(NotificationEventCode::OrderPlaced, 'order', $result->order->id);
        $this->assertEventCount(NotificationEventCode::OrderPlaced, 1);
    }

    public function test_order_and_payment_lifecycle_dispatches_only_actual_transitions(): void
    {
        Event::fake([CommerceEventOccurred::class]);
        [$cancelled] = $this->orderWithInventory();
        app(OrderStatusService::class)->cancel($cancelled);

        $this->assertEvent(NotificationEventCode::PaymentCancelled, 'order_payment', $cancelled->payment->id);
        $this->assertEvent(NotificationEventCode::OrderCancelled, 'order', $cancelled->id);

        Event::fake([CommerceEventOccurred::class]);
        [$failed] = $this->orderWithInventory();
        app(PaymentStatusService::class)->markFailed($failed);
        $this->assertEvent(NotificationEventCode::PaymentFailed, 'order_payment', $failed->payment->id);

        Event::fake([CommerceEventOccurred::class]);
        [$completed] = $this->orderWithInventory();
        $orders = app(OrderStatusService::class);
        $payments = app(PaymentStatusService::class);
        $orders->process($completed);
        $orders->markOutForDelivery($completed->fresh());
        $payments->markPaid($completed->fresh());
        $orders->fulfill($completed->fresh());
        $this->assertEvent(NotificationEventCode::PaymentPaid, 'order_payment', $completed->payment->id);
        $this->assertEvent(NotificationEventCode::OrderCompleted, 'order', $completed->id);
        $this->assertEventCount(NotificationEventCode::OrderCompleted, 1);
    }

    public function test_delivery_failure_is_distinct_from_normal_order_cancellation(): void
    {
        Event::fake([CommerceEventOccurred::class]);
        [$order] = $this->orderWithInventory();
        $service = app(OrderStatusService::class);
        $service->process($order);
        $service->markOutForDelivery($order->fresh());
        $service->markDeliveryFailed($order->fresh());

        $this->assertEvent(NotificationEventCode::PaymentCancelled, 'order_payment', $order->payment->id);
        $this->assertEvent(NotificationEventCode::DeliveryFailed, 'order', $order->id);
        $this->assertEventCount(NotificationEventCode::DeliveryFailed, 1);
        $this->assertEventCount(NotificationEventCode::OrderCancelled, 0);
    }

    public function test_cancellation_request_events_follow_each_successful_state_change(): void
    {
        Event::fake([CommerceEventOccurred::class]);
        $customer = User::factory()->customer()->create();
        [$order] = $this->orderWithInventory(customer: $customer);
        $service = app(OrderCancellationRequestService::class);
        $administrator = User::factory()->create();
        $request = $service->create($order, $customer, 'Please cancel');
        $this->assertEvent(
            NotificationEventCode::CancellationRequestSubmitted,
            'order_cancellation_request',
            $request->id
        );

        Event::fake([CommerceEventOccurred::class]);
        $service->reject($order, $request, $administrator, 'Not approved');
        $this->assertEvent(
            NotificationEventCode::CancellationRequestRejected,
            'order_cancellation_request',
            $request->id
        );

        Event::fake([CommerceEventOccurred::class]);
        $second = $service->create($order, $customer, 'Please reconsider');
        Event::fake([CommerceEventOccurred::class]);
        $service->approve($order, $second, $administrator);
        $this->assertEvent(
            NotificationEventCode::CancellationRequestApproved,
            'order_cancellation_request',
            $second->id
        );
        $this->assertEvent(NotificationEventCode::OrderCancelled, 'order', $order->id);
    }

    private function assertEvent(NotificationEventCode $code, string $entityType, int $entityId): void
    {
        Event::assertDispatched(CommerceEventOccurred::class, fn ($event) => $event->event === $code
            && $event->entityType === $entityType
            && $event->entityId === $entityId
        );
    }

    private function assertEventCount(NotificationEventCode $code, int $expected): void
    {
        $this->assertCount(
            $expected,
            Event::dispatched(CommerceEventOccurred::class)
                ->filter(fn (array $payload) => $payload[0]->event === $code)
        );
    }

    private function orderWithInventory(?User $customer = null): array
    {
        $product = Product::factory()->create(['price' => 10]);
        $product->inventory()->create([
            'quantity' => 10, 'average_cost' => 4, 'low_stock_alert' => null,
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'customer@example.com',
            'customer_first_name' => $customer?->first_name ?? 'Test',
            'customer_last_name' => $customer?->last_name ?? 'Customer',
            'locale' => 'en', 'currency_code' => 'USD', 'status' => 'pending',
            'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery', 'requires_payment_before_processing' => false,
            'subtotal' => 10, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'grand_total' => 10, 'placed_at' => now(),
        ]);
        OrderItem::query()->create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_type' => 'simple', 'sku' => $product->sku, 'name' => 'Snapshot Product',
            'quantity' => 1, 'original_unit_price' => 10, 'unit_price' => 10,
            'tax_amount' => 0, 'row_subtotal' => 10, 'row_total' => 10,
            'unit_cost' => null, 'is_inventory_item' => true,
        ]);
        OrderPayment::query()->create([
            'payment_number' => 'PAY-2026-'.fake()->unique()->numerify('######'),
            'order_id' => $order->id, 'payment_method_id' => null,
            'method_code' => 'cash_on_delivery', 'method_name' => 'Cash on Delivery',
            'method_type' => 'offline', 'status' => 'pending', 'amount' => 10,
            'currency_code' => 'USD', 'paid_amount' => 0,
        ]);

        return [$order->fresh('payment'), $product];
    }

    private function checkoutScenario(): array
    {
        $tax = Tax::query()->create(['name' => 'Tax', 'rate' => 10, 'status' => true]);
        foreach ([
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2c', 'string'],
            ['tax', 'default_tax_id', (string) $tax->id, 'integer'],
            ['cart', 'lifetime_days', '30', 'integer'],
        ] as [$group, $key, $value, $type]) {
            Setting::query()->create(compact('group', 'key', 'value', 'type'));
        }
        $customer = User::factory()->customer()->create();
        $cart = Cart::query()->create([
            'user_id' => $customer->id, 'guest_token_hash' => null,
            'last_activity_at' => now(), 'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple, 'price' => 100,
            'use_default_tax' => true, 'status' => true, 'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en', 'name' => 'Product', 'url_key' => 'product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => 10, 'average_cost' => 20, 'low_stock_alert' => 1,
        ]);
        $cart->items()->create([
            'product_id' => $product->id, 'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id), 'quantity' => 1,
        ]);

        return [
            $cart,
            $customer,
            ShippingMethod::factory()->create(['amount' => 5, 'is_active' => true]),
            PaymentMethod::factory()->create(['is_active' => true]),
        ];
    }

    private function checkoutData(ShippingMethod $shipping, PaymentMethod $payment): array
    {
        $address = [
            'first_name' => 'Jane', 'last_name' => 'Customer', 'company' => null,
            'email' => 'jane@example.com', 'phone' => '70123456',
            'address_line_1' => 'Main Street', 'address_line_2' => null,
            'city' => 'Beirut', 'state' => 'Beirut', 'postal_code' => null, 'country_code' => 'LB',
        ];

        return [
            'shipping_method' => $shipping->code, 'payment_method' => $payment->code,
            'customer' => ['first_name' => 'Jane', 'last_name' => 'Customer', 'phone' => '70123456', 'email' => 'jane@example.com'],
            'address_source' => 'manual', 'manual_address' => $address,
        ];
    }
}
