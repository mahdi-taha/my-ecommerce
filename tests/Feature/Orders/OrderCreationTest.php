<?php

namespace Tests\Feature\Orders;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_snapshots_a_non_prepayment_method(): void
    {
        $method = $this->paymentMethod('cash_on_delivery', false);

        $order = app(OrderService::class)->create($this->orderData($method->code));

        $this->assertSame($method->code, $order->payment_method);
        $this->assertFalse($order->requires_payment_before_processing);
        $this->assertSame(OrderStatus::Pending->value, $order->status);
        $this->assertSame(PaymentStatus::Pending->value, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Unfulfilled->value, $order->fulfillment_status);
        $this->assertMatchesRegularExpression('/^ORD-\d{4}-000001$/', $order->order_number);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'method' => $method->code,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_order_creation_uses_the_global_document_sequence(): void
    {
        $method = $this->paymentMethod('cash_on_delivery', false);

        $first = app(OrderService::class)->create($this->orderData($method->code));
        $second = app(OrderService::class)->create($this->orderData($method->code));

        $this->assertSame(1, (int) str($first->order_number)->afterLast('-')->toString());
        $this->assertSame(2, (int) str($second->order_number)->afterLast('-')->toString());
    }

    public function test_order_creation_snapshots_a_prepayment_required_method(): void
    {
        $method = $this->paymentMethod('online_card', true);

        $order = app(OrderService::class)->create($this->orderData($method->code));

        $this->assertSame($method->code, $order->payment_method);
        $this->assertTrue($order->requires_payment_before_processing);
        $this->assertSame($method->code, $order->payments()->firstOrFail()->method);
    }

    public function test_order_creation_ignores_supplied_inventory_cost(): void
    {
        $method = $this->paymentMethod('cash_on_delivery', false);
        $data = $this->orderData($method->code);
        $data['items'][0]['is_inventory_item'] = true;
        $data['items'][0]['unit_cost'] = 999;

        $order = app(OrderService::class)->create($data);

        $this->assertNull($order->items()->firstOrFail()->unit_cost);
    }

    public function test_order_creation_rejects_an_inactive_payment_method(): void
    {
        $method = $this->paymentMethod('disabled_method', false, false);

        try {
            app(OrderService::class)->create($this->orderData($method->code));
            $this->fail('An inactive payment method was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The selected payment method is unavailable.'],
                $exception->errors()['payment_method']
            );
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_payments', 0);
        }
    }

    public function test_order_creation_rejects_an_unknown_payment_method(): void
    {
        try {
            app(OrderService::class)->create($this->orderData('unknown'));
            $this->fail('An unknown payment method was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The selected payment method is unavailable.'],
                $exception->errors()['payment_method']
            );
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('order_payments', 0);
        }
    }

    private function paymentMethod(
        string $code,
        bool $requiresPrepayment,
        bool $active = true
    ): PaymentMethod {
        return PaymentMethod::create([
            'code' => $code,
            'name' => str($code)->replace('_', ' ')->title(),
            'is_active' => $active,
            'requires_payment_before_processing' => $requiresPrepayment,
            'sort_order' => 1,
        ]);
    }

    private function orderData(string $paymentMethod): array
    {
        return [
            'user_id' => null,
            'customer_email' => 'customer@example.com',
            'customer_first_name' => 'Test',
            'customer_last_name' => 'Customer',
            'customer_phone' => null,
            'locale' => 'en',
            'currency_code' => 'USD',
            'payment_method' => $paymentMethod,
            'subtotal' => 10,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 10,
            'customer_notes' => null,
            'admin_notes' => null,
            'placed_at' => now(),
            'items' => [[
                'product_id' => null,
                'product_type' => 'simple',
                'sku' => 'SNAPSHOT-SKU',
                'product_number' => null,
                'name' => 'Snapshot Product',
                'option_summary' => null,
                'image_path' => null,
                'configuration' => null,
                'quantity' => 1,
                'original_unit_price' => 10,
                'unit_price' => 10,
                'tax_amount' => 0,
                'row_subtotal' => 10,
                'row_total' => 10,
                'unit_cost' => null,
                'is_inventory_item' => false,
            ]],
            'billing_address' => $this->address(),
            'shipping_address' => $this->address(),
            'payment' => [
                'method' => 'ignored-caller-value',
                'amount' => 10,
                'transaction_reference' => null,
                'failure_message' => null,
            ],
        ];
    }

    private function address(): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'company' => null,
            'email' => 'customer@example.com',
            'phone' => null,
            'address_line_1' => 'Test Street',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => null,
            'postal_code' => null,
            'country_code' => 'LB',
        ];
    }
}
