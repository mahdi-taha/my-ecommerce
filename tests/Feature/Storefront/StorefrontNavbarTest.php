<?php

namespace Tests\Feature\Storefront;

use App\Enums\CartItemType;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wishlist;
use Database\Seeders\SettingSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('href="'.route('shop.products.index').'"', false)
            ->assertSee('href="'.route('shop.categories.show', 'first-root').'"', false)
            ->assertSee(__('shop.navigation.shop'))
            ->assertDontSee(__('shop.navigation.contact'))
            ->assertSeeInOrder(['First Root', 'Child', 'Grandchild', 'Second Root'])
            ->assertDontSee('Great Grandchild')
            ->assertSee('data-category-navigation-desktop', false)
            ->assertSee('storefront-category-submenu', false)
            ->assertSee('data-category-navigation-mobile', false)
            ->assertSee('storefront-mobile-category-toggle', false)
            ->assertDontSee('Inactive')
            ->assertDontSee('Arabic only')
            ->assertDontSee('(3)')
            ->assertDontSee('single.html')
            ->assertDontSee('shop.html')
            ->assertDontSee('contact.html');

        $this->assertTrue($second->exists);
        $this->assertStringContainsString(route('shop.categories.show', 'first-root'), $response->getContent());
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
            ->assertSee('storefront-category-desktop-list', false)
            ->assertSee('storefront-category-submenu', false)
            ->assertSee('id="mobileCategoriesMenu"', false)
            ->assertSee('data-bs-toggle="collapse"', false)
            ->assertSee('storefront-mobile-category-toggle', false);

        $script = file_get_contents(resource_path('js/shop/category-mega-menu.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("addEventListener('mouseenter'", $script);
        $this->assertStringContainsString("addEventListener('focusin'", $script);
        $this->assertStringContainsString('Dropdown.getOrCreateInstance', $script);
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

    public function test_mobile_brand_uses_the_configured_store_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('store/logo.png', 'logo');
        $this->setSetting('store_name', 'Configured Store');
        $this->setSetting('store_logo_path', 'store/logo.png');

        $content = $this->get(route('shop.home'))->assertOk()->getContent();
        $brand = $this->mobileBrand($content);

        $this->assertStringContainsString('src="'.Storage::disk('public')->url('store/logo.png').'"', $brand);
        $this->assertStringContainsString(__('shop.topbar.store_logo', ['store' => 'Configured Store']), $brand);
        $this->assertStringNotContainsString('fa-shopping-bag', $brand);
    }

    public function test_mobile_brand_falls_back_to_the_configured_store_name_when_logo_is_missing(): void
    {
        Storage::fake('public');
        $this->setSetting('store_name', 'Fallback Store');

        foreach (['', 'store/missing-logo.png'] as $logoPath) {
            $this->setSetting('store_logo_path', $logoPath);

            $content = $this->get(route('shop.home', ['locale' => 'ar']))->assertOk()->getContent();
            $brand = $this->mobileBrand($content);

            $this->assertStringContainsString('<bdi>Fallback Store</bdi>', $brand);
            $this->assertStringNotContainsString('<img', $brand);
            $this->assertStringNotContainsString('fa-shopping-bag', $brand);
            $this->assertStringContainsString('data-bs-target="#navbarCollapse"', $content);
        }
    }

    public function test_topbar_and_navbar_share_store_identity_queries(): void
    {
        Cache::forget('setting.store.store_name');
        Cache::forget('setting.store.store_logo_path');
        Cache::forget('setting.currency.default_currency');
        $identityQueries = ['store_name' => 0, 'store_logo_path' => 0, 'default_currency' => 0];

        DB::listen(function (QueryExecuted $query) use (&$identityQueries): void {
            if (! str_contains(strtolower($query->sql), 'from "settings"')) {
                return;
            }

            foreach (array_keys($identityQueries) as $key) {
                if (in_array($key, $query->bindings, true)) {
                    $identityQueries[$key]++;
                }
            }
        });

        $this->get(route('shop.home'))->assertOk();

        $this->assertSame(['store_name' => 1, 'store_logo_path' => 1, 'default_currency' => 1], $identityQueries);
    }

    public function test_mobile_preferences_show_the_shared_language_switcher_and_read_only_currency(): void
    {
        $this->setSetting('default_currency', 'LBP', 'currency');

        $response = $this->get(route('shop.home'));

        $response->assertOk()
            ->assertSee('d-lg-none align-items-center justify-content-between gap-3 storefront-mobile-preferences', false)
            ->assertSee('storefront-mobile-currency', false)
            ->assertSee('<bdi dir="ltr">LBP</bdi>', false)
            ->assertSee('data-bs-toggle="dropdown"', false)
            ->assertDontSee('<select', false)
            ->assertDontSee('name="currency"', false)
            ->assertSee('storefront-mobile-categories-toggle', false);
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

        $this->view('shop.components.navbar')->assertSee('data-category-navigation-desktop', false);

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

    private function setSetting(string $key, ?string $value, string $group = 'store'): void
    {
        Setting::query()->where('group', $group)->where('key', $key)->update(['value' => $value]);
        Cache::forget("setting.{$group}.{$key}");
    }

    private function mobileBrand(string $content): string
    {
        preg_match('/<a[^>]+class="navbar-brand d-block d-lg-none"[^>]*>(.*?)<\/a>/s', $content, $matches);

        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }
}
