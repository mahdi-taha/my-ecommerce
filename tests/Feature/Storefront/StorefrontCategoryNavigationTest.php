<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCategoryNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingSeeder::class);
    }

    public function test_desktop_and_mobile_render_three_ordered_levels_with_real_category_links(): void
    {
        $laterRoot = $this->category('Later Root', 'الجذر اللاحق', position: 2);
        $root = $this->category('Root', 'الجذر', position: 1);
        $laterChild = $this->category('Later Child', 'الابن اللاحق', position: 2, parent: $root);
        $child = $this->category('Child', 'الابن', position: 1, parent: $root);
        $grandchild = $this->category('Grandchild', 'الحفيد', parent: $child);
        $greatGrandchild = $this->category('Fourth Level', 'المستوى الرابع', parent: $grandchild);

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('data-category-navigation-desktop', false)
            ->assertSee('class="storefront-category-root-scrollport"', false)
            ->assertSee('class="storefront-category-flyout-layer"', false)
            ->assertSee('class="storefront-category-flyout storefront-category-flyout--level-2"', false)
            ->assertSee('class="storefront-category-flyout storefront-category-flyout--level-3"', false)
            ->assertSee('data-category-navigation-mobile', false)
            ->assertSee('class="btn storefront-mobile-category-toggle"', false)
            ->assertSee('href="'.route('shop.categories.show', ['slug' => 'root']).'"', false)
            ->assertSee('href="'.route('shop.categories.show', ['slug' => 'child']).'"', false)
            ->assertSee('href="'.route('shop.categories.show', ['slug' => 'grandchild']).'"', false)
            ->assertDontSee($greatGrandchild->translations()->where('locale', 'en')->value('name'))
            ->assertSeeInOrder(['Root', 'Child', 'Later Child', 'Later Root']);

        $this->assertTrue($laterRoot->exists);
        $this->assertTrue($laterChild->exists);
    }

    public function test_arabic_navigation_uses_localized_names_and_urls_without_fallback(): void
    {
        $root = $this->category('Root', 'الجذر');
        $child = $this->category('Child', 'الابن', parent: $root);
        $grandchild = $this->category('Grandchild', 'الحفيد', parent: $child);

        $response = $this->get(route('shop.home', ['locale' => 'ar']));

        $response->assertOk()
            ->assertSee('الجذر')
            ->assertSee('الابن')
            ->assertSee('الحفيد')
            ->assertSee(route('shop.categories.show', ['locale' => 'ar', 'slug' => 'ar-root']), false)
            ->assertSee(route('shop.categories.show', ['locale' => 'ar', 'slug' => 'ar-child']), false)
            ->assertDontSee('>Root<', false);

        $this->assertTrue($grandchild->exists);
    }

    public function test_desktop_hover_focus_and_mobile_collapse_hooks_preserve_link_navigation(): void
    {
        $root = $this->category('Root', 'الجذر');
        $this->category('Child', 'الابن', parent: $root);

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('data-bs-toggle="collapse"', false)
            ->assertSee('aria-controls="mobile-category-'.$root->id.'"', false)
            ->assertSee('data-category-flyout-trigger="root-'.$root->id.'"', false)
            ->assertSee('aria-controls="category-flyout-root-'.$root->id.'"', false)
            ->assertSee('data-category-flyout="root-'.$root->id.'"', false)
            ->assertSee('href="'.route('shop.categories.show', ['slug' => 'root']).'"', false);

        $script = file_get_contents(resource_path('js/shop/category-mega-menu.js'));
        $css = file_get_contents(resource_path('css/shop.css'));

        $this->assertIsString($script);
        $this->assertIsString($css);
        $this->assertStringContainsString("addEventListener('mouseenter'", $script);
        $this->assertStringContainsString("addEventListener('focusin'", $script);
        $this->assertStringContainsString("addEventListener('resize'", $script);
        $this->assertStringContainsString("addEventListener('scroll'", $script);
        $this->assertStringContainsString('trigger.getBoundingClientRect()', $script);
        $this->assertStringContainsString('flyout.getBoundingClientRect()', $script);
        $this->assertStringContainsString('Math.min(Math.max(triggerRect.top, safeTop), maximumTop)', $script);
        $this->assertStringContainsString('viewportSafetyMargin = 16', $script);
        $this->assertStringContainsString('closeDelay = 150', $script);
        $this->assertStringContainsString('window.setTimeout', $script);
        $this->assertStringContainsString('cancelPendingClose()', $script);
        $this->assertStringContainsString('--storefront-category-available-height', $script);
        $this->assertStringContainsString('--storefront-category-flyout-top', $script);
        $this->assertStringContainsString('.storefront-category-root-scrollport,', $css);
        $this->assertStringContainsString('overflow-y: auto', $css);
        $this->assertStringContainsString('.storefront-category-menu .storefront-category-panel-menu', $css);
        $this->assertStringContainsString('overflow: visible', $css);
        $this->assertStringContainsString('top: var(--storefront-category-flyout-top, 0)', $css);
        $this->assertStringContainsString('[dir="rtl"] .storefront-category-flyout-layer', $css);
    }

    private function category(
        string $englishName,
        string $arabicName,
        int $position = 0,
        ?Category $parent = null
    ): Category {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'level' => $parent ? $parent->level + 1 : 0,
            'position' => $position,
        ]);
        $category->translations()->createMany([
            ['locale' => 'en', 'name' => $englishName, 'slug' => str($englishName)->slug()],
            ['locale' => 'ar', 'name' => $arabicName, 'slug' => 'ar-'.str($englishName)->slug()],
        ]);

        return $category;
    }
}
