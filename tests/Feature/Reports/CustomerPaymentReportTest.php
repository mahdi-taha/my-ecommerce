<?php

namespace Tests\Feature\Reports;

use App\DTOs\Reports\ReportFilters;
use App\Enums\AccountType;
use App\Models\User;
use App\Services\Reports\CustomersReportQuery;
use App\Services\Reports\PaymentsReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class CustomerPaymentReportTest extends TestCase
{
    use CreatesRefundOrders, RefreshDatabase;

    public function test_registrations_exclude_manual_customers(): void
    {
        User::factory()->create(['account_type' => AccountType::Customer, 'has_account' => true]);
        User::factory()->create(['account_type' => AccountType::Customer, 'has_account' => false]);
        $this->assertSame(1, app(CustomersReportQuery::class)->summary($this->filters())['registrations']);
    }

    public function test_payment_report_uses_obligation_not_attempts(): void
    {
        $this->paidRefundOrder();
        $row = app(PaymentsReportQuery::class)->rows($this->filters())->items()[0];
        $this->assertEquals(100, $row->collected);
        $this->assertEquals(100, $row->obligation_amount);
    }

    private function filters(): ReportFilters
    {
        return new ReportFilters(null, null, null, null, null, null, null, null, null, null, null, null, 25);
    }
}
