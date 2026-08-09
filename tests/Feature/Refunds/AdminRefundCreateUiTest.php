<?php

namespace Tests\Feature\Refunds;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRefundOrders;
use Tests\TestCase;

class AdminRefundCreateUiTest extends TestCase
{
    use CreatesRefundOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_unselected_create_page_renders_accessible_order_lookup_without_an_order_id_field(): void
    {
        [, , $admin] = $this->paidRefundOrder();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.refunds.create'))
            ->assertOk()
            ->assertSee('data-refund-order-lookup', false)
            ->assertSee('data-lookup-url="'.route('admin.refunds.lookups.orders').'"', false)
            ->assertSee('id="refund-order-search"', false)
            ->assertSee('type="search"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertDontSee('>Order ID<', false)
            ->assertDontSee('name="order"', false);
    }

    public function test_refund_lookup_script_uses_native_links_and_safe_text_rendering(): void
    {
        $script = file_get_contents(resource_path('js/admin/refunds.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("document.createElement('a')", $script);
        $this->assertStringContainsString('textContent', $script);
        $this->assertStringContainsString('replaceChildren', $script);
        $this->assertStringContainsString('AbortController', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }
}
