<?php

namespace Tests\Feature\CustomerAccount;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_uses_the_storefront_shell_and_shared_sidebar(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.account.edit'));

        $response->assertOk()
            ->assertSee('storefront-topbar', false)
            ->assertSee('storefront-navbar', false)
            ->assertSee('data-customer-account-carousel', false)
            ->assertSee('data-item-count="8"', false)
            ->assertSee(route('customer.account.edit'), false)
            ->assertSee(route('customer.addresses.index'), false)
            ->assertSee(route('shop.account.orders.index'), false)
            ->assertSee(route('shop.account.reviews.index'), false)
            ->assertSee(route('shop.wishlist.index'), false)
            ->assertSee(route('shop.account.notifications.index'), false)
            ->assertSee(route('customer.account.password.edit'), false)
            ->assertSee(route('customer.logout'), false)
            ->assertSee('method="POST"', false)
            ->assertSee('bi bi-person', false)
            ->assertSee('bi bi-box-arrow-right', false)
            ->assertSee('aria-current="page"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_profile_and_change_password_have_independent_active_states(): void
    {
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.account.password.edit'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('customer.account.password.edit'), '/').'"[^>]*aria-current="page"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/href="'.preg_quote(route('customer.account.edit'), '/').'"[^>]*aria-current="page"/',
            $html
        );
    }

    public function test_account_pages_share_the_nested_storefront_layout(): void
    {
        $views = [
            'customer/account/edit.blade.php',
            'customer/account/password.blade.php',
            'customer/account/addresses/index.blade.php',
            'customer/account/addresses/create.blade.php',
            'customer/account/addresses/edit.blade.php',
            'customer/account/orders/index.blade.php',
            'customer/account/orders/show.blade.php',
            'customer/account/notifications/index.blade.php',
            'shop/pages/wishlist.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));

            $this->assertNotFalse($contents);
            $this->assertStringContainsString("@extends('customer.account.layout')", $contents, $view);
        }

        $layout = file_get_contents(resource_path('views/customer/account/layout.blade.php'));

        $this->assertNotFalse($layout);
        $this->assertStringContainsString("@extends('shop.layouts.app')", $layout);
        $this->assertStringContainsString("@include('customer.account._navigation')", $layout);
    }

    public function test_arabic_account_layout_uses_rtl_document_direction(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'customer')
            ->withSession(['storefront_locale' => 'ar'])
            ->get(route('customer.account.edit', ['locale' => 'ar']))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.account.navigation.profile'))
            ->assertSee(__('shop.account.navigation.previous'))
            ->assertSee(__('shop.account.navigation.next'));
    }

    public function test_account_navigation_uses_progressive_owl_enhancement(): void
    {
        $navigation = file_get_contents(resource_path('views/customer/account/_navigation.blade.php'));
        $script = file_get_contents(resource_path('js/shop/customer-account-carousel.js'));
        $entry = file_get_contents(resource_path('js/shop.js'));
        $css = file_get_contents(resource_path('css/shop.css'));

        $this->assertIsString($navigation);
        $this->assertIsString($script);
        $this->assertIsString($entry);
        $this->assertIsString($css);
        $this->assertStringContainsString('data-customer-account-carousel', $navigation);
        $this->assertStringNotContainsString('class="nav nav-pills storefront-account-nav-carousel owl-carousel"', $navigation);
        $this->assertSame(8, substr_count($navigation, 'storefront-account-nav-slide'));
        $this->assertStringContainsString("classList.add('owl-carousel')", $script);
        $this->assertStringContainsString('loop: false', $script);
        $this->assertStringContainsString('rewind: false', $script);
        $this->assertStringContainsString('autoplay: false', $script);
        $this->assertStringContainsString('dots: false', $script);
        $this->assertStringContainsString('mouseDrag: true', $script);
        $this->assertStringContainsString('touchDrag: true', $script);
        $this->assertStringContainsString('startPosition: activeIndex', $script);
        $this->assertStringContainsString("document.documentElement.dir === 'rtl'", $script);
        $this->assertStringContainsString('nav: itemCount > capacity', $script);
        $this->assertStringContainsString('initializeCustomerAccountCarousel();', $entry);
        $this->assertStringContainsString('overflow-x: auto', $css);
        $this->assertStringContainsString('.storefront-account-nav-carousel.owl-loaded', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-account-nav-carousel', $css);
    }
}
