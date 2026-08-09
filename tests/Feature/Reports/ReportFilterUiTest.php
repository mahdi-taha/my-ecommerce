<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_report_uses_business_controls_without_currency_or_raw_ids(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.show', 'orders'));

        $response->assertOk()
            ->assertSee('name="customer_id"', false)
            ->assertSee('class="form-select report-entity-select"', false)
            ->assertSee('name="order_status"', false)
            ->assertSee('name="payment_status"', false)
            ->assertSee('name="fulfillment_status"', false)
            ->assertDontSee('name="currency"', false)
            ->assertDontSee('Customer ID')
            ->assertDontSee('Product ID')
            ->assertDontSee('Administrator ID')
            ->assertDontSee('name="administrator_id"', false)
            ->assertDontSee('name="shipping_treatment"', false);
    }

    public function test_refunds_report_keeps_refund_only_filters_out_of_mixed_reports(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);

        $this->actingAs($admin, 'admin')->get(route('admin.reports.show', 'refunds'))
            ->assertOk()
            ->assertSee('name="administrator_id"', false)
            ->assertSee('name="shipping_treatment"', false)
            ->assertDontSee('name="customer_id"', false)
            ->assertDontSee('name="order_status"', false);

        $this->get(route('admin.reports.show', 'sales'))
            ->assertOk()
            ->assertDontSee('name="administrator_id"', false)
            ->assertDontSee('name="shipping_treatment"', false);
    }

    public function test_inventory_report_has_only_product_and_bounded_pagination_controls(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);

        $this->actingAs($admin, 'admin')->get(route('admin.reports.show', 'inventory'))
            ->assertOk()
            ->assertSee('name="product_id"', false)
            ->assertSee('name="per_page"', false)
            ->assertSee('value="25"', false)
            ->assertSee('value="50"', false)
            ->assertSee('value="100"', false)
            ->assertDontSee('value="500"', false)
            ->assertDontSee('value="1000"', false)
            ->assertDontSee('name="date_from"', false)
            ->assertDontSee('name="date_to"', false);
    }

    public function test_filters_persist_selected_entity_dates_enums_and_page_size(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        $customer = User::factory()->customer()->create([
            'name' => 'Persistent Customer',
            'email' => 'persistent@example.test',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.show', [
            'report' => 'orders',
            'customer_id' => $customer->id,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-09',
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'fulfillment_status' => 'fulfilled',
            'per_page' => 50,
        ]));

        $response->assertOk()
            ->assertSee('value="'.$customer->id.'" selected', false)
            ->assertSee('Persistent Customer — persistent@example.test')
            ->assertSee('name="date_from" value="2026-08-01"', false)
            ->assertSee('name="date_to" value="2026-08-09"', false)
            ->assertSee('value="completed" selected', false)
            ->assertSee('value="paid" selected', false)
            ->assertSee('value="fulfilled" selected', false)
            ->assertSee('value="50" selected', false);
    }

    public function test_clear_filters_and_export_use_clean_report_specific_queries(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.reports.show', [
            'report' => 'sales',
            'payment_status' => 'paid',
            'shipping_treatment' => 'company_absorbs',
            'page' => 3,
        ]));

        $response->assertOk()
            ->assertSee('href="'.route('admin.reports.show', 'sales').'"', false)
            ->assertSee(route('admin.reports.export', ['report' => 'sales', 'payment_status' => 'paid', 'per_page' => 25]))
            ->assertDontSee('shipping_treatment=company_absorbs', false)
            ->assertDontSee('page=3', false);
    }
}
