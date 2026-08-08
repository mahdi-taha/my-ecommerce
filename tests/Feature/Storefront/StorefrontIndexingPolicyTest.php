<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductType;
use App\Models\CmsPage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorefrontIndexingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_canonical_pages_are_indexable(): void
    {
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => 10,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Camera', 'url_key' => 'camera']);
        $page = CmsPage::query()->create(['code' => 'about', 'is_active' => true, 'sort_order' => 0]);
        $page->translations()->create(['locale' => 'en', 'title' => 'About', 'slug' => 'about', 'body' => 'About']);
        Cache::flush();

        foreach ([
            route('shop.home', ['locale' => 'en']),
            route('shop.products.index', ['locale' => 'en']),
            route('shop.products.index', ['locale' => 'en', 'page' => 2]),
            route('shop.products.top-selling', ['locale' => 'en']),
            route('shop.products.top-selling', ['locale' => 'en', 'page' => 2]),
            route('shop.products.show', ['locale' => 'en', 'url_key' => 'camera']),
            route('shop.pages.show', ['locale' => 'en', 'slug' => 'about']),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee($this->robots('index,follow'), false);
        }
    }

    public function test_filtered_listings_and_private_storefront_pages_are_not_indexable(): void
    {
        $this->get(route('shop.products.index', ['locale' => 'en', 'q' => 'camera']))->assertOk()
            ->assertSee($this->robots('noindex,nofollow'), false);
        $this->get(route('shop.products.top-selling', ['locale' => 'en', 'q' => 'camera']))->assertOk()
            ->assertSee($this->robots('noindex,nofollow'), false);
        $this->get(route('shop.cart.index', ['locale' => 'en']))->assertOk()
            ->assertSee($this->robots('noindex,nofollow'), false);

        foreach (['customer.login', 'customer.register', 'customer.password.request'] as $route) {
            $this->get(route($route, ['locale' => 'en']))->assertOk()
                ->assertSee($this->robots('noindex,nofollow'), false);
        }
        $this->get(route('customer.password.reset', [
            'locale' => 'en',
            'token' => 'test-token',
            'email' => 'customer@example.test',
        ]))->assertOk()->assertSee($this->robots('noindex,nofollow'), false);
    }

    public function test_authenticated_customer_pages_inherit_defensive_noindex_default(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer, 'customer');

        foreach ([
            'shop.wishlist.index',
            'shop.account.notifications.index',
            'shop.account.orders.index',
            'shop.account.reviews.index',
            'customer.account.edit',
            'customer.addresses.index',
            'customer.account.password.edit',
        ] as $route) {
            $this->get(route($route, ['locale' => 'en']))->assertOk()
                ->assertSee($this->robots('noindex,nofollow'), false);
        }
    }

    private function robots(string $policy): string
    {
        return '<meta name="robots" content="'.$policy.'">';
    }
}
