<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class ReportCsvExportTest extends TestCase
{
    use CreatesRefundOrders, RefreshDatabase;

    public function test_csv_streams_the_same_report_columns_as_utf8(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'has_account' => true, 'is_active' => true]);
        [$order] = $this->paidRefundOrder();
        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.export', ['report' => 'orders']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString($order->order_number, $content);
        $this->assertStringContainsString('Currency', $content);
    }

    public function test_csv_escapes_spreadsheet_formula_prefixes(): void
    {
        $service = new ReportExportService;
        $method = new \ReflectionMethod($service, 'safe');
        $this->assertSame("'=danger", $method->invoke($service, '=danger'));
    }

    public function test_csv_exports_every_filtered_row_beyond_the_interactive_page_size(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'is_active' => true]);
        for ($index = 1; $index <= 105; $index++) {
            Order::query()->create([
                'order_number' => sprintf('ORD-EXPORT-%04d', $index),
                'customer_email' => 'export@example.test',
                'customer_first_name' => 'Export',
                'customer_last_name' => 'Customer',
                'locale' => 'en',
                'currency_code' => 'USD',
                'status' => 'completed',
                'payment_status' => PaymentStatus::Paid->value,
                'fulfillment_status' => 'fulfilled',
                'payment_method' => 'cash_on_delivery',
                'subtotal' => '10.0000',
                'discount_total' => '0.0000',
                'shipping_total' => '0.0000',
                'tax_total' => '0.0000',
                'grand_total' => '10.0000',
                'placed_at' => now(),
                'paid_at' => now(),
            ]);
        }

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.export', [
            'report' => 'orders',
            'per_page' => 25,
        ]));

        $content = $response->streamedContent();
        $this->assertStringContainsString('ORD-EXPORT-0001', $content);
        $this->assertStringContainsString('ORD-EXPORT-0105', $content);
        $this->assertSame(106, substr_count(trim($content), "\n") + 1);
    }
}
