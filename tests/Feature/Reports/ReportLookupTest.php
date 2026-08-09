<?php

namespace Tests\Feature\Reports;

use App\Enums\AccountType;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lookups_are_admin_protected_and_bounded(): void
    {
        $this->getJson(route('admin.reports.lookups.customers'))->assertUnauthorized();
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        User::factory()->count(25)->customer()->create();

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.reports.lookups.customers'))
            ->assertOk()
            ->assertJsonCount(20, 'results');
    }

    public function test_customer_lookup_includes_registered_and_manual_customers_without_admins(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin, 'name' => 'Not Customer']);
        $registered = User::factory()->customer()->create(['name' => 'Registered Buyer', 'email' => 'registered@example.test']);
        $manual = User::factory()->manualCustomer()->create(['name' => 'Manual Buyer']);

        $response = $this->actingAs($admin, 'admin')->getJson(route('admin.reports.lookups.customers', ['q' => 'Buyer']));

        $response->assertOk()
            ->assertJsonFragment(['id' => $registered->id, 'text' => 'Registered Buyer — registered@example.test'])
            ->assertJsonFragment(['id' => $manual->id, 'text' => 'Manual Buyer (Manual)'])
            ->assertJsonMissing(['id' => $admin->id]);
    }

    public function test_product_lookup_uses_current_name_and_sku_without_storefront_eligibility(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        $product = Product::factory()->create(['sku' => 'REPORT-SKU', 'status' => false]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Archived Report Product', 'url_key' => 'archived-report-product']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.reports.lookups.products', ['q' => 'REPORT-SKU']))
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id, 'text' => 'Archived Report Product — REPORT-SKU']);
    }

    public function test_category_lookup_returns_three_level_admin_paths(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        $root = $this->category('Electronics', 0);
        $child = $this->category('Phones', 1, $root);
        $leaf = $this->category('Android', 2, $child);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.reports.lookups.categories', ['q' => 'Android']))
            ->assertOk()
            ->assertJsonFragment(['id' => $leaf->id, 'text' => 'Electronics > Phones > Android']);
    }

    public function test_administrator_lookup_includes_inactive_admins_and_excludes_customers(): void
    {
        $admin = User::factory()->create(['account_type' => AccountType::Admin]);
        $inactive = User::factory()->inactive()->create([
            'account_type' => AccountType::Admin,
            'name' => 'Historical Admin',
            'email' => 'historical-admin@example.test',
        ]);
        $customer = User::factory()->customer()->create(['name' => 'Historical Customer']);

        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.reports.lookups.administrators', ['q' => 'Historical']))
            ->assertOk()
            ->assertJsonFragment(['id' => $inactive->id, 'text' => 'Historical Admin — historical-admin@example.test (Inactive)'])
            ->assertJsonMissing(['id' => $customer->id]);
    }

    private function category(string $name, int $level, ?Category $parent = null): Category
    {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'level' => $level,
        ]);
        $category->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);

        return $category;
    }
}
