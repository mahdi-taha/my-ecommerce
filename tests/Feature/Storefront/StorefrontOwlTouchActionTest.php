<?php

namespace Tests\Feature\Storefront;

use Tests\TestCase;

class StorefrontOwlTouchActionTest extends TestCase
{
    public function test_known_horizontal_owl_carousels_declare_scoped_touch_behavior(): void
    {
        $css = file_get_contents(resource_path('css/shop.css'));

        $this->assertIsString($css);

        foreach ([
            '.header-carousel',
            '.productList-carousel',
            '.productImg-carousel',
            '.single-carousel',
            '.related-carousel',
            '.storefront-category-carousel',
            '.storefront-account-nav-carousel',
        ] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }

        $this->assertMatchesRegularExpression(
            '/\.header-carousel,.*?\.storefront-account-nav-carousel\s*\{\s*touch-action:\s*pan-y pinch-zoom;\s*\}/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/(?:^|[},])\s*\.owl-carousel\s*\{[^}]*touch-action:/m',
            $css
        );
    }

    public function test_owl_version_and_touch_drag_initializers_remain_unchanged(): void
    {
        $owl = file_get_contents(public_path('storefront-assets/lib/owlcarousel/owl.carousel.js'));
        $main = file_get_contents(public_path('storefront-assets/js/main.js'));
        $categories = file_get_contents(resource_path('js/shop/homepage-category-carousel.js'));
        $account = file_get_contents(resource_path('js/shop/customer-account-carousel.js'));

        $this->assertIsString($owl);
        $this->assertIsString($main);
        $this->assertIsString($categories);
        $this->assertIsString($account);
        $this->assertStringContainsString('Owl Carousel v2.2.1', $owl);
        $this->assertStringContainsString('this.$stage.on(\'touchstart.owl.core\'', $owl);
        $this->assertStringContainsString('touchmove.owl.core', $owl);
        $this->assertStringContainsString('event.preventDefault()', $owl);

        foreach ([
            '.header-carousel',
            '.productList-carousel',
            '.productImg-carousel',
            '.single-carousel',
            '.related-carousel',
        ] as $selector) {
            $this->assertStringContainsString('$("'.$selector.'").owlCarousel(', $main);
        }

        $this->assertStringContainsString('touchDrag: true', $categories);
        $this->assertStringContainsString('touchDrag: true', $account);
    }
}
