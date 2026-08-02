<?php

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class LocalizedStorefrontRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_localized_urls_are_deterministic_and_override_the_session(): void
    {
        $this->withSession(['storefront_locale' => 'ar'])->get('/en')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSessionHas('storefront_locale', 'en');

        $this->withSession(['storefront_locale' => 'en'])->get('/ar')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSessionHas('storefront_locale', 'ar');
    }

    public function test_route_generation_uses_the_active_locale_without_affecting_admin(): void
    {
        $this->get('/ar')->assertOk();
        $this->assertSame(url('/ar/shop'), route('shop.products.index'));

        URL::defaults(['locale' => null]);
        $this->assertSame(url('/admin/login'), route('admin.login'));
    }

    public function test_unsupported_locale_is_not_a_storefront_route(): void
    {
        $this->get('/fr/shop')->assertNotFound();
    }
}
