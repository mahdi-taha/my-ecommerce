<?php

namespace Tests\Feature\Refunds;

use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class AdminRefundPrintTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    public function test_admin_details_links_to_customer_safe_snapshot_print_document(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/print-logo.png', 'logo');
        foreach ([
            'store_name' => 'Printable Store',
            'store_address' => 'Store Address',
            'store_email' => 'store@example.test',
            'store_phone' => '+961 1 234 567',
            'store_logo_path' => 'store/print-logo.png',
        ] as $key => $value) {
            Setting::query()->updateOrCreate(['group' => 'store', 'key' => $key], ['value' => $value, 'type' => 'text']);
            Cache::forget("setting.store.{$key}");
        }

        $product = Product::factory()->create(['sku' => 'LIVE-SKU']);
        [$order, $payment, $admin] = $this->paidRefundOrder([
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Buyer',
            'customer_email' => 'snapshot@example.test',
            'customer_phone' => '+96170111222',
            'placed_at' => '2026-08-01 09:30:00',
        ]);
        $admin->update(['name' => 'Hidden Administrator']);
        $item = $this->refundOrderItem($order, [
            'product_id' => $product->id,
            'name' => 'Snapshot Product Name',
            'sku' => 'SNAPSHOT-SKU',
            'unit_price' => '75.0000',
            'option_summary' => 'Legacy summary',
        ]);
        $item->options()->create([
            'attribute_code' => 'color', 'attribute_name' => 'Color',
            'option_code' => 'blue', 'option_label' => 'Blue',
        ]);
        $refund = $this->refund($order->id, $payment->id, $admin->id, $item->id, '5.0000');
        PaymentAttempt::factory()->create([
            'order_payment_id' => $payment->id,
            'provider' => 'private-provider',
            'metadata_json' => ['secret' => 'PRIVATE-PROVIDER-PAYLOAD'],
        ]);
        $product->update(['sku' => 'CHANGED-LIVE-SKU']);

        $this->actingAs($admin, 'admin')->get(route('admin.refunds.show', $refund))
            ->assertOk()
            ->assertSee(route('admin.refunds.print', $refund), false)
            ->assertSee('target="_blank" rel="noopener"', false);

        $response = $this->get(route('admin.refunds.print', $refund));

        $response->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('onclick="window.print()"', false)
            ->assertSee('Printable Store')
            ->assertSee('Store Address')
            ->assertSee('store@example.test')
            ->assertSee('+961 1 234 567')
            ->assertSee(Storage::disk('public')->url('store/print-logo.png'), false)
            ->assertSee($refund->refund_number)
            ->assertSee('Completed')
            ->assertSee($order->order_number)
            ->assertSee('2026-08-01 09:30')
            ->assertSee('Snapshot Buyer')
            ->assertSee('snapshot@example.test')
            ->assertSee('+96170111222')
            ->assertSee('Snapshot Product Name')
            ->assertSee('SNAPSHOT-SKU')
            ->assertSee('Color: Blue')
            ->assertSee(format_store_price('75.0000', 'USD'))
            ->assertSee(format_store_price('95.0000', 'USD'))
            ->assertSee(format_store_price('5.0000', 'USD'))
            ->assertSee(format_store_price('90.0000', 'USD'))
            ->assertDontSee('CHANGED-LIVE-SKU')
            ->assertDontSee('TOP-SECRET-INTERNAL-NOTE')
            ->assertDontSee('PRIVATE-PROVIDER-PAYLOAD')
            ->assertDontSee('private-provider')
            ->assertDontSee('Hidden Administrator')
            ->assertDontSee($refund->idempotency_key)
            ->assertDontSee('Company shipping loss')
            ->assertDontSee(format_store_price('77.0000', 'USD'))
            ->assertDontSee('admin-sidebar', false);

        $printView = file_get_contents(resource_path('views/refunds/print.blade.php'));
        $this->assertIsString($printView);
        $this->assertStringContainsString("@vite('resources/css/refund-print.css')", $printView);
        $printCss = file_get_contents(resource_path('css/refund-print.css'));
        $this->assertIsString($printCss);
        $this->assertMatchesRegularExpression(
            '/@media print\s*\{.*?\.refund-print-actions\s*\{\s*display: none !important;/s',
            $printCss
        );
    }

    public function test_zero_shipping_deduction_is_omitted_and_missing_logo_is_safe(): void
    {
        [$order, $payment, $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order, ['option_summary' => 'Size: Large']);
        $refund = $this->refund($order->id, $payment->id, $admin->id, $item->id, '0.0000');
        Setting::query()->updateOrCreate(
            ['group' => 'store', 'key' => 'store_logo_path'],
            ['value' => 'store/missing.png', 'type' => 'text']
        );
        Cache::forget('setting.store.store_logo_path');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.refunds.print', $refund));

        $response->assertOk()
            ->assertSee('Size: Large')
            ->assertDontSee(__('shop.refund_print.shipping_deduction'))
            ->assertDontSee('store/missing.png', false);
    }

    public function test_guest_and_customer_cannot_access_admin_refund_print(): void
    {
        [$order, $payment, $admin] = $this->paidRefundOrder();
        $item = $this->refundOrderItem($order);
        $refund = $this->refund($order->id, $payment->id, $admin->id, $item->id, '0.0000');

        $this->get(route('admin.refunds.print', $refund))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('admin.refunds.print', $refund))
            ->assertRedirect(route('admin.login'));
    }

    private function refund(int $orderId, int $paymentId, int $adminId, int $itemId, string $deduction): Refund
    {
        $refund = Refund::query()->create([
            'refund_number' => 'RFD-2026-'.fake()->unique()->numerify('######'),
            'idempotency_key' => hash('sha256', fake()->unique()->uuid()),
            'order_id' => $orderId, 'order_payment_id' => $paymentId, 'currency_code' => 'USD',
            'merchandise_subtotal' => '100.0000', 'discount_amount' => '5.0000',
            'tax_amount' => '0.0000', 'merchandise_amount' => '95.0000',
            'return_shipping_cost' => $deduction, 'shipping_treatment' => 'deduct_from_refund',
            'shipping_deduction' => $deduction, 'company_shipping_loss' => '77.0000',
            'customer_refund_amount' => (float) $deduction > 0 ? '90.0000' : '95.0000',
            'internal_note' => 'TOP-SECRET-INTERNAL-NOTE', 'created_by' => $adminId,
            'refunded_at' => '2026-08-02 17:45:00',
        ]);
        $refund->items()->create([
            'order_item_id' => $itemId, 'quantity' => '1.0000',
            'subtotal_amount' => '100.0000', 'discount_amount' => '5.0000',
            'tax_amount' => '0.0000', 'line_amount' => '95.0000',
        ]);

        return $refund;
    }
}
