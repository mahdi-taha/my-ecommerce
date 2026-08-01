<?php

namespace Tests\Feature\Accessibility;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibilityMarkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_authentication_pages_expose_localized_document_and_autocomplete_semantics(): void
    {
        $this->get(route('customer.login'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('autocomplete="current-password"', false);

        $this->get(route('customer.register'))
            ->assertOk()
            ->assertSee('autocomplete="given-name"', false)
            ->assertSee('autocomplete="family-name"', false)
            ->assertSee('autocomplete="tel"', false)
            ->assertSee('autocomplete="new-password"', false);

        app()->setLocale('ar');

        $this->get(route('customer.password.request'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('autocomplete="email"', false);
    }

    public function test_navigation_and_image_templates_expose_stable_accessible_names(): void
    {
        $navigation = $this->viewSource('customer/account/_navigation.blade.php');
        $wishlist = $this->viewSource('shop/pages/wishlist.blade.php');
        $hero = $this->viewSource('shop/components/hero.blade.php');

        $this->assertStringContainsString('aria-current="page"', $navigation);
        $this->assertStringContainsString('aria-label="{{ __(\'shop.account.navigation.label\') }}"', $navigation);
        $this->assertStringContainsString('alt="{{ $translation?->name ?? $product->sku }}"', $wishlist);
        $this->assertStringContainsString('alt="{{ __(\'shop.hero.image_alt\') }}"', $hero);
    }

    public function test_admin_controls_and_customer_and_admin_tables_have_stable_semantics(): void
    {
        $adminTopbar = $this->viewSource('components/admin-topbar.blade.php');
        $adminSidebar = $this->viewSource('components/admin-sidebar.blade.php');
        $settings = $this->viewSource('admin/settings/index.blade.php');
        $customerOrders = $this->viewSource('customer/account/orders/index.blade.php');
        $adminOrders = $this->viewSource('admin/orders/show.blade.php');

        $this->assertStringContainsString('aria-label="Open navigation"', $adminTopbar);
        $this->assertStringContainsString('aria-label="Open administrator account menu"', $adminTopbar);
        $this->assertStringContainsString('aria-label="Close navigation"', $adminSidebar);
        $this->assertStringContainsString('for="store_name"', $settings);
        $this->assertStringContainsString('id="store_name"', $settings);
        $this->assertStringContainsString('<th scope="col">', $customerOrders);
        $this->assertStringContainsString('<th scope="col">', $adminOrders);
    }

    private function viewSource(string $relativePath): string
    {
        $contents = file_get_contents(resource_path('views/'.$relativePath));

        $this->assertNotFalse($contents);

        return $contents;
    }
}
