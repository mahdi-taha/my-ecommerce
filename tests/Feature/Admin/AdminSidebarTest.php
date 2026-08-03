<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_links_every_implemented_top_level_admin_module_without_placeholders(): void
    {
        $response = $this->actingAs(User::factory()->create(), 'admin')
            ->get(route('admin.products.index'));

        $response->assertOk();

        foreach ([
            'admin.products.index',
            'admin.categories.index',
            'admin.attributes.index',
            'admin.inventory.index',
            'admin.orders.index',
            'admin.shipping-methods.index',
            'admin.customers.index',
            'admin.coupons.index',
            'admin.notifications.index',
            'admin.settings.index',
            'admin.homepage-services.index',
        ] as $routeName) {
            $response->assertSee(route($routeName), false);
        }

        $response
            ->assertDontSee('href="#"', false)
            ->assertDontSeeText('Dashboard')
            ->assertDontSeeText('Analytical')
            ->assertDontSeeText('eCommerce')
            ->assertDontSeeText('Payment Methods')
            ->assertDontSeeText('Cancellation Requests');
    }

    public function test_catalog_routes_expand_the_collapse_and_activate_the_matching_child(): void
    {
        $admin = User::factory()->create();

        foreach ([
            'admin.products.create' => 'admin.products.index',
            'admin.categories.create' => 'admin.categories.index',
            'admin.attributes.create' => 'admin.attributes.index',
            'admin.inventory.history' => 'admin.inventory.index',
        ] as $currentRoute => $navigationRoute) {
            $response = $this->actingAs($admin, 'admin')->get(route($currentRoute));

            $response
                ->assertOk()
                ->assertSee('data-bs-toggle="collapse"', false)
                ->assertSee('data-bs-target="#catalog-navigation"', false)
                ->assertSee('aria-expanded="true" aria-controls="catalog-navigation"', false)
                ->assertSee('id="catalog-navigation" class="collapse first-level show"', false);

            $this->assertActiveLink($response->getContent(), route($navigationRoute));
        }
    }

    public function test_direct_module_links_have_precise_independent_active_states(): void
    {
        $admin = User::factory()->create();

        foreach ([
            'admin.orders.index',
            'admin.shipping-methods.index',
            'admin.customers.index',
            'admin.coupons.index',
            'admin.notifications.index',
            'admin.settings.index',
            'admin.homepage-services.index',
        ] as $routeName) {
            $response = $this->actingAs($admin, 'admin')->get(route($routeName));

            $response
                ->assertOk()
                ->assertSee('aria-expanded="false" aria-controls="catalog-navigation"', false)
                ->assertSee('id="catalog-navigation" class="collapse first-level"', false);

            $this->assertActiveLink($response->getContent(), route($routeName));
        }
    }

    public function test_sidebar_uses_bounded_route_families_for_nested_resource_pages(): void
    {
        $sidebar = file_get_contents(resource_path('views/components/admin-sidebar.blade.php'));

        foreach ([
            "request()->routeIs('admin.products.*')",
            "request()->routeIs('admin.categories.*')",
            "request()->routeIs('admin.attributes.*', 'admin.attribute-options.*')",
            "request()->routeIs('admin.inventory.*')",
            "request()->routeIs('admin.orders.*')",
            "request()->routeIs('admin.shipping-methods.*')",
            "request()->routeIs('admin.customers.*')",
            "request()->routeIs('admin.coupons.*')",
            "request()->routeIs('admin.notifications.*')",
            "request()->routeIs('admin.settings.*')",
            "request()->routeIs('admin.homepage-services.*')",
        ] as $routeMatcher) {
            $this->assertStringContainsString($routeMatcher, $sidebar);
        }
    }

    private function assertActiveLink(string $html, string $url): void
    {
        $pattern = '/<a class="sidebar-link justify-content-between [^"]*active[^"]*"\s+href="'.preg_quote($url, '/').'"/';

        $this->assertMatchesRegularExpression($pattern, $html);
    }
}
