<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admins_can_access_the_report_directory(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
        $customer = User::factory()->create(['account_type' => AccountType::Customer]);
        $this->actingAs($customer, 'customer')->get(route('admin.reports.index'))->assertRedirect(route('admin.login'));
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'has_account' => true, 'is_active' => true]);
        $this->actingAs($admin, 'admin')->get(route('admin.reports.index'))->assertOk()->assertSee('Sales')->assertSee('Reviews');
    }

    public function test_report_filter_validation_rejects_invalid_boundaries_and_page_sizes(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'has_account' => true, 'is_active' => true]);
        $this->actingAs($admin, 'admin')->get(route('admin.reports.show', ['report' => 'sales', 'date_from' => '2026-08-10', 'date_to' => '2026-08-01', 'per_page' => 1000]))
            ->assertSessionHasErrors(['date_to', 'per_page']);
    }
}
