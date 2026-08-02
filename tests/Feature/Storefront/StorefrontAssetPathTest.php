<?php

namespace Tests\Feature\Storefront;

use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAssetPathTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_shop_route_is_not_shadowed_by_a_public_directory(): void
    {
        $this->assertSame('/shop', route('shop.products.index', absolute: false));
        $this->assertDirectoryDoesNotExist(public_path('shop'));

        $this->get(route('shop.products.index'))->assertOk();
    }

    public function test_storefront_renders_only_renamed_asset_urls(): void
    {
        $response = $this->get(route('shop.home'))->assertOk();

        $response
            ->assertSee(asset('storefront-assets/css/bootstrap.min.css'), false)
            ->assertSee(asset('storefront-assets/css/style.css'), false)
            ->assertSee(asset('storefront-assets/lib/animate/animate.min.css'), false)
            ->assertSee(asset('storefront-assets/lib/owlcarousel/assets/owl.carousel.min.css'), false)
            ->assertDontSee('/shop/css/', false)
            ->assertDontSee('/shop/js/', false)
            ->assertDontSee('/shop/img/', false)
            ->assertDontSee('/shop/lib/', false);
    }

    public function test_required_storefront_asset_tree_was_moved_intact(): void
    {
        foreach ([
            'css/bootstrap.min.css',
            'css/style.css',
            'img/carousel-1.png',
            'img/product-1.png',
            'js/main.js',
            'lib/animate/animate.min.css',
            'lib/lightbox/css/lightbox.min.css',
            'lib/lightbox/images/loading.gif',
            'lib/owlcarousel/owl.carousel.min.js',
            'lib/owlcarousel/assets/owl.video.play.png',
            'lib/waypoints/links.php',
            'lib/waypoints/waypoints.min.js',
            'lib/wow/wow.min.js',
        ] as $relativePath) {
            $this->assertFileExists(public_path('storefront-assets/'.$relativePath));
        }
    }
}
