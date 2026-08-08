<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Reports\ReportRegistry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_report_renders_empty_state_and_exposes_shared_filters(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'has_account' => true, 'is_active' => true]);
        foreach (app(ReportRegistry::class)->names() as $report) {
            $this->actingAs($admin, 'admin')->get(route('admin.reports.show', $report))->assertOk()->assertSee('No results.')->assertSee('Export CSV')->assertSee('Payment Status');
        }
    }

    public function test_empty_report_pages_have_a_bounded_query_ceiling(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'has_account' => true, 'is_active' => true]);
        $count = 0;
        DB::listen(function (QueryExecuted $query) use (&$count) {
            $count++;
        });
        foreach (app(ReportRegistry::class)->names() as $report) {
            $before = $count;
            $this->actingAs($admin, 'admin')->get(route('admin.reports.show', $report))->assertOk();
            $this->assertLessThanOrEqual(15, $count - $before, "$report exceeded its query ceiling");
        }
    }
}
