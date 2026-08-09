<?php

namespace Tests\Feature\Storefront;

use App\Enums\CartItemType;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHeaderRtlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_arabic_header_exposes_rtl_hooks_and_isolates_mixed_direction_values(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Arabic Customer']);
        $product = Product::factory()->create();
        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'rtl-header-product'),
            'quantity' => 2,
        ]);
        Wishlist::query()->create(['user_id' => $customer->id])
            ->items()->create(['product_id' => $product->id]);

        $response = $this->withSession(['storefront_locale' => 'ar'])
            ->actingAs($customer, 'customer')
            ->get(route('shop.home', ['locale' => 'ar']));

        $response->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('storefront-topbar', false)
            ->assertSee('storefront-header-main', false)
            ->assertSee('storefront-navbar', false)
            ->assertSee('storefront-header-badge', false)
            ->assertSee('<bdi dir="ltr">USD</bdi>', false)
            ->assertSee('<bdi>Arabic Customer</bdi>', false)
            ->assertSee('placeholder="'.__('shop.navigation.search_placeholder').'"', false)
            ->assertSee('aria-label="'.__('shop.navigation.search_label').'"', false);
    }

    public function test_english_header_preserves_ltr_document_and_localized_search_contract(): void
    {
        $this->withSession(['storefront_locale' => 'en'])
            ->get(route('shop.home'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('placeholder="Search Looking For?"', false)
            ->assertSee('aria-label="Search products"', false);
    }

    public function test_shop_styles_scope_header_corrections_to_rtl_documents(): void
    {
        $css = file_get_contents(resource_path('css/shop.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-topbar', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-header-badge', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-navbar', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-menu .storefront-category-panel-menu', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-flyout-layer', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-flyout', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-forward-icon', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-carousel', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-carousel.owl-carousel .owl-prev i', $css);
        $this->assertStringContainsString('border-inline-start', $css);
        $this->assertStringNotContainsString('[dir="ltr"] .storefront-navbar', $css);

        $script = file_get_contents(resource_path('js/shop/homepage-category-carousel.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("rtl: document.documentElement.dir === 'rtl'", $script);
    }
}
