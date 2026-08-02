<?php

namespace Tests\Feature\Storefront;

use App\Enums\CartItemType;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontNavbarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_navigation_uses_v1_routes_and_localized_active_category_hierarchy(): void
    {
        $second = $this->category('Second Root', position: 2);
        $first = $this->category('First Root', position: 1);
        $child = $this->category('Child', position: 1, parent: $first);
        $grandchild = $this->category('Grandchild', position: 1, parent: $child);
        $this->category('Great Grandchild', position: 1, parent: $grandchild);
        $this->category('Child', position: 2, parent: $first, slug: 'second-child');
        $this->category('Inactive', position: 0, active: false);
        $this->category('Arabic only', position: 0, locale: 'ar');

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('href="'.route('shop.home').'"', false)
            ->assertSee(__('shop.navigation.shop'))
            ->assertSee(__('shop.navigation.contact'))
            ->assertSee('href="#"', false)
            ->assertSeeInOrder(['First Root', 'Child', 'Grandchild', 'Great Grandchild', 'Second Root'])
            ->assertSee('data-category-navigation-data', false)
            ->assertSee('data-category-navigation-desktop', false)
            ->assertSee('data-category-root-panel', false)
            ->assertSee('data-category-children-panel', false)
            ->assertSee('data-category-detail-panel', false)
            ->assertSee('data-category-navigation-mobile', false)
            ->assertSee('data-mobile-category-level', false)
            ->assertDontSee('Inactive')
            ->assertDontSee('Arabic only')
            ->assertDontSee('(3)')
            ->assertDontSee('single.html')
            ->assertDontSee('shop.html')
            ->assertDontSee('contact.html');

        $this->assertTrue($second->exists);
        $this->assertMatchesRegularExpression(
            '/<script type="application\/json" data-category-navigation-data>(.*?)<\/script>/s',
            $response->getContent()
        );
        preg_match(
            '/<script type="application\/json" data-category-navigation-data>(.*?)<\/script>/s',
            $response->getContent(),
            $matches
        );
        $navigation = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($first->id, $navigation[0]['id']);
        $this->assertSame('Great Grandchild', $navigation[0]['children'][0]['children'][0]['children'][0]['name']);
        $this->assertSame($second->id, $navigation[1]['id']);
        $this->assertSame([], $navigation[1]['children']);
    }

    public function test_category_menu_exposes_accessible_desktop_and_mobile_interaction_hooks(): void
    {
        $root = $this->category('Root');
        $this->category('Leaf', parent: $root);

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('id="categoryMegaMenuToggle"', false)
            ->assertSee('aria-controls="categoryMegaMenu"', false)
            ->assertSee('data-bs-auto-close="outside"', false)
            ->assertSee('id="categoryRootPanel"', false)
            ->assertSee('id="categoryChildrenPanel"', false)
            ->assertSee('id="categoryDetailPanel"', false)
            ->assertSee('data-category-detail-breadcrumb', false)
            ->assertSee('data-category-detail-back', false)
            ->assertSee(__('shop.navigation.no_subcategories'))
            ->assertSee('id="mobileCategoriesMenu"', false)
            ->assertSee('data-bs-toggle="collapse"', false)
            ->assertSee('data-mobile-category-breadcrumb', false)
            ->assertSee('data-mobile-category-back', false);

        $script = file_get_contents(resource_path('js/shop/category-mega-menu.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("addEventListener('mouseenter'", $script);
        $this->assertStringContainsString("addEventListener('focus'", $script);
        $this->assertStringContainsString("event.key === 'Home'", $script);
        $this->assertStringContainsString("event.key === 'Enter'", $script);
        $this->assertStringContainsString("event.key !== 'Escape'", $script);
        $this->assertStringContainsString("document.documentElement.dir === 'rtl'", $script);
        $this->assertStringContainsString('`${panelId}-category-${category.id}`', $script);
        $this->assertStringContainsString('path.push(category)', $script);
        $this->assertStringContainsString('toggle.focus()', $script);
        $this->assertFileDoesNotExist(resource_path('views/shop/components/category-tree.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/shop/components/category-mega-branch.blade.php'));
    }

    public function test_mobile_customer_navigation_reuses_shared_cart_and_wishlist_counts(): void
    {
        $customer = User::factory()->customer()->create(['name' => 'Navbar Customer']);
        $product = Product::factory()->create();
        $cart = Cart::query()->create([
            'user_id' => $customer->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'navbar-product'),
            'quantity' => 2,
        ]);
        Wishlist::query()->create(['user_id' => $customer->id])
            ->items()->create(['product_id' => $product->id]);

        $response = $this->actingAs($customer, 'customer')->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('Navbar Customer')
            ->assertSee(route('shop.cart.index'), false)
            ->assertSee(route('shop.wishlist.index'), false)
            ->assertSee(route('customer.account.edit'), false)
            ->assertSee(route('customer.addresses.index'), false)
            ->assertSee(route('shop.account.orders.index'), false)
            ->assertSee(route('shop.account.notifications.index'), false)
            ->assertSee(route('customer.account.password.edit'), false)
            ->assertSee('method="POST" action="'.route('customer.logout').'"', false)
            ->assertSee('<span class="badge bg-secondary rounded-pill">2</span>', false)
            ->assertSee('<span class="badge bg-secondary rounded-pill">1</span>', false);

        $this->assertSame(2, $response->viewData('storefrontCartQuantity'));
        $this->assertSame(1, $response->viewData('storefrontWishlistCount'));
    }

    public function test_guest_navigation_uses_login_for_wishlist_and_hides_zero_badges(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee(route('customer.login'), false)
            ->assertSee(route('customer.register'), false)
            ->assertDontSee('<span class="badge bg-secondary rounded-pill">0</span>', false);
    }

    public function test_category_hierarchy_uses_a_fixed_number_of_queries(): void
    {
        $root = $this->category('Root');
        $child = $this->category('Child', parent: $root);
        $this->category('Grandchild', parent: $child);
        $categoryQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'from "categories"') || str_contains($sql, 'from "category_translations"')) {
                $categoryQueries++;
            }
        });

        $this->view('shop.components.navbar')->assertSee('data-category-navigation-data', false);

        $this->assertSame(2, $categoryQueries);
    }

    private function category(
        string $name,
        int $position = 0,
        ?Category $parent = null,
        bool $active = true,
        string $locale = 'en',
        ?string $slug = null
    ): Category {
        $category = Category::factory()->create([
            'parent_id' => $parent?->id,
            'position' => $position,
            'level' => $parent ? $parent->level + 1 : 0,
            'status' => $active,
        ]);
        $category->translations()->create([
            'locale' => $locale,
            'name' => $name,
            'slug' => $slug ?? str($name)->slug(),
        ]);

        return $category;
    }
}
