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
            ->assertSee('nav nav-pills flex-column', false)
            ->assertSee(route('customer.account.edit'), false)
            ->assertSee(route('customer.addresses.index'), false)
            ->assertSee(route('shop.account.orders.index'), false)
            ->assertSee(route('shop.wishlist.index'), false)
            ->assertSee(route('shop.account.notifications.index'), false)
            ->assertSee(route('customer.account.password.edit'), false)
            ->assertSee(route('customer.logout'), false)
            ->assertSee('method="POST"', false)
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
            ->get(route('customer.account.edit'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee(__('shop.account.navigation.profile'));
    }
}
