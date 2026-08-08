<?php

namespace Tests\Feature\Orders;

use App\Enums\ShippingTreatment;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedOrderPrintPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_uses_snapshots_structural_context_and_customer_safe_refunds(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/logo.png', 'logo');
        foreach ([
            'store_name' => 'Current Store',
            'store_email' => 'store@example.test',
            'store_phone' => '+9611000000',
            'store_address' => 'Current Store Address',
            'store_logo_path' => 'store/logo.png',
        ] as $key => $value) {
            Setting::query()->create(['group' => 'store', 'key' => $key, 'value' => $value, 'type' => 'text']);
            cache()->forget('setting.store.'.$key);
        }

        $product = Product::factory()->create(['sku' => 'LIVE-SKU', 'price' => '999.0000']);
        $shippingMethod = ShippingMethod::factory()->create(['name' => 'Live Shipping']);
        $paymentMethod = PaymentMethod::factory()->create(['name' => 'Live Payment']);
        $order = $this->order($product, $shippingMethod, $paymentMethod);
        $refund = $this->refund($order);
        $product->update(['sku' => 'CHANGED-SKU', 'price' => '1.0000']);
        $shippingMethod->update(['name' => 'Changed Shipping']);
        $paymentMethod->update(['name' => 'Changed Payment']);

        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.orders.print', $order));

        $response->assertOk()
            ->assertSee('Current Store')->assertSee('Current Store Address')
            ->assertSee('Snapshot Customer')->assertSee('snapshot@example.test')
            ->assertSee('Same as billing address')
            ->assertSee('Bundle Context')->assertSee('Snapshot Product')->assertSee('SNAPSHOT-SKU')
            ->assertSee('Color: Black')->assertSee('$ 90.00')->assertSee('$ 9.00')
            ->assertSee('Snapshot Shipping')->assertSee('Snapshot Payment')->assertSee('$ 95.00')
            ->assertSee($refund->refund_number)->assertSee('$ 40.00')->assertSee('$ 5.00')->assertSee('$ 35.00')
            ->assertDontSee('CHANGED-SKU')->assertDontSee('Changed Shipping')->assertDontSee('Changed Payment')
            ->assertDontSee('Secret internal note')->assertDontSee('Company shipping loss')
            ->assertDontSee($refund->idempotency_key)->assertDontSee('Sensitive provider reference')
            ->assertSee('onclick="window.print()"', false)
            ->assertSee('content="noindex,nofollow"', false)
            ->assertDontSee('storefront-navbar')->assertDontSee('admin-sidebar');

        $css = file_get_contents(resource_path('css/order-print.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString('.order-print-actions', $css);
        $this->assertStringContainsString('display: none !important', $css);
        $this->assertStringContainsString('overflow-wrap: anywhere', $css);
        $this->assertStringContainsString('break-inside: avoid', $css);
    }

    private function order(Product $product, ShippingMethod $shippingMethod, PaymentMethod $paymentMethod): Order
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-PRINT-100001', 'customer_email' => 'snapshot@example.test',
            'customer_first_name' => 'Snapshot', 'customer_last_name' => 'Customer',
            'customer_phone' => '70123456', 'locale' => 'en', 'currency_code' => 'USD',
            'status' => 'completed', 'payment_status' => 'partially_refunded',
            'fulfillment_status' => 'fulfilled', 'payment_method' => 'snapshot_payment',
            'requires_payment_before_processing' => false, 'subtotal' => '90.0000',
            'discount_total' => '5.0000', 'shipping_total' => '6.0000',
            'tax_total' => '9.0000', 'grand_total' => '100.0000', 'placed_at' => now(),
        ]);
        $address = [
            'first_name' => 'Snapshot', 'last_name' => 'Customer', 'company' => 'Snapshot Company',
            'email' => 'address@example.test', 'phone' => '70123456',
            'address_line_1' => 'Snapshot Street', 'address_line_2' => 'Floor 2',
            'city' => 'Beirut', 'state' => 'Beirut', 'postal_code' => '1107', 'country_code' => 'LB',
        ];
        $order->addresses()->create(['type' => 'billing', ...$address]);
        $order->addresses()->create(['type' => 'shipping', ...$address]);
        $order->shipping()->create([
            'shipping_method_id' => $shippingMethod->id, 'shipping_method_code' => 'snapshot_shipping',
            'shipping_method_name' => 'Snapshot Shipping', 'shipping_method_type' => 'delivery',
            'shipping_amount' => '6.0000',
        ]);
        $payment = $order->payment()->create([
            'payment_number' => 'PAY-PRINT-100001', 'payment_method_id' => $paymentMethod->id,
            'method_code' => 'snapshot_payment', 'method_name' => 'Snapshot Payment',
            'method_type' => 'offline', 'amount' => '100.0000', 'currency_code' => 'USD',
            'status' => 'partially_refunded', 'paid_amount' => '95.0000', 'paid_at' => now(),
        ]);
        PaymentAttempt::query()->create([
            'order_payment_id' => $payment->id, 'attempt_number' => 1, 'status' => 'paid',
            'amount' => '100.0000', 'currency_code' => 'USD',
            'transaction_reference' => 'Sensitive provider reference',
        ]);
        $parent = $order->items()->create($this->itemData([
            'product_id' => null, 'product_type' => 'bundle', 'sku' => 'BUNDLE',
            'name' => 'Bundle Context', 'quantity' => '1.0000', 'unit_price' => '0.0000',
            'row_subtotal' => '0.0000', 'row_total' => '0.0000', 'is_inventory_item' => false,
        ]));
        $item = $order->items()->create($this->itemData([
            'parent_order_item_id' => $parent->id, 'product_id' => $product->id,
            'product_type' => 'bundle_item', 'sku' => 'SNAPSHOT-SKU', 'name' => 'Snapshot Product',
            'option_summary' => 'Legacy Option', 'quantity' => '1.0000',
            'original_unit_price' => '95.0000', 'unit_price' => '90.0000',
            'row_subtotal' => '90.0000', 'discount_amount' => '5.0000',
            'tax_amount' => '9.0000', 'row_total' => '94.0000', 'is_inventory_item' => true,
        ]));
        $item->options()->create([
            'attribute_code' => 'color', 'attribute_name' => 'Color',
            'option_code' => 'black', 'option_label' => 'Black',
        ]);

        return $order;
    }

    private function refund(Order $order): Refund
    {
        return Refund::query()->create([
            'refund_number' => 'RFD-PRINT-100001', 'idempotency_key' => str_repeat('f', 64),
            'order_id' => $order->id, 'order_payment_id' => $order->payment->id,
            'currency_code' => 'USD', 'merchandise_subtotal' => '40.0000',
            'discount_amount' => '0.0000', 'tax_amount' => '0.0000',
            'merchandise_amount' => '40.0000', 'return_shipping_cost' => '5.0000',
            'shipping_treatment' => ShippingTreatment::DeductFromRefund,
            'shipping_deduction' => '5.0000', 'company_shipping_loss' => '0.0000',
            'customer_refund_amount' => '35.0000', 'internal_note' => 'Secret internal note',
            'created_by' => User::factory()->create()->id, 'refunded_at' => now(),
        ]);
    }

    private function itemData(array $overrides): array
    {
        return array_merge([
            'parent_order_item_id' => null, 'product_id' => null, 'product_type' => 'simple',
            'sku' => 'SKU', 'name' => 'Item', 'option_summary' => null, 'quantity' => '1.0000',
            'original_unit_price' => '0.0000', 'unit_price' => '0.0000', 'tax_name' => null,
            'tax_rate' => '0.0000', 'tax_amount' => '0.0000', 'row_subtotal' => '0.0000',
            'discount_amount' => '0.0000', 'row_total' => '0.0000', 'is_inventory_item' => false,
        ], $overrides);
    }
}
