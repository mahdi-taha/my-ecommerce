<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountType;
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
}
