<?php

namespace Tests\Feature\Storefront;

use App\Enums\AccountType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_add_lazily_creates_one_wishlist_and_is_duplicate_safe(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->product();

        $this->actingAs($customer, 'customer')
            ->post(route('shop.wishlist.store'), ['product_id' => $product->id])
            ->assertRedirect()
            ->assertSessionHas('success', __('shop.wishlist.added'));
        $this->post(route('shop.wishlist.store'), ['product_id' => $product->id])
            ->assertRedirect();

        $this->assertDatabaseCount('wishlists', 1);
        $this->assertDatabaseCount('wishlist_items', 1);
        $this->assertSame($customer->id, Wishlist::firstOrFail()->user_id);
    }

    public function test_database_constraints_protect_single_wishlist_and_duplicate_items(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->product();
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        $wishlist->items()->create(['product_id' => $product->id]);

        try {
            Wishlist::create(['user_id' => $customer->id]);
            $this->fail('A customer received more than one Wishlist.');
        } catch (QueryException) {
            $this->assertDatabaseCount('wishlists', 1);
        }

        $this->expectException(QueryException::class);
        $wishlist->items()->create(['product_id' => $product->id]);
    }

    public function test_remove_is_customer_scoped_and_idempotent(): void
    {
        $owner = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $product = $this->product();
        $wishlist = Wishlist::create(['user_id' => $owner->id]);
        $wishlist->items()->create(['product_id' => $product->id]);

        $this->actingAs($other, 'customer')
            ->delete(route('shop.wishlist.destroy', $product))
            ->assertRedirect();
        $this->assertDatabaseHas('wishlist_items', ['wishlist_id' => $wishlist->id]);

        $this->actingAs($owner, 'customer')
            ->delete(route('shop.wishlist.destroy', $product))
            ->assertRedirect()
            ->assertSessionHas('success', __('shop.wishlist.removed'));
        $this->delete(route('shop.wishlist.destroy', $product))->assertRedirect();
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_guests_are_redirected_and_read_only_requests_do_not_create_wishlists(): void
    {
        $product = $this->product();

        $this->get(route('shop.wishlist.index'))->assertRedirect(route('customer.login'));
        $this->post(route('shop.wishlist.store'), ['product_id' => $product->id])
            ->assertRedirect(route('customer.login'));
        $this->delete(route('shop.wishlist.destroy', $product))
            ->assertRedirect(route('customer.login'));
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertViewHas('storefrontWishlistCount', 0);
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_add_rejects_ineligible_products_submitted_directly(): void
    {
        $customer = User::factory()->customer()->create();
        $products = [
            $this->product(['status' => false]),
            $this->product(['is_visible_individually' => false]),
            $this->product(['type' => 'unsupported']),
        ];
        $parent = $this->product(['type' => ProductType::Configurable->value]);
        $products[] = $this->product([
            'configurable_id' => $parent->id,
            'is_visible_individually' => false,
        ]);

        foreach ($products as $product) {
            $this->actingAs($customer, 'customer')
                ->post(route('shop.wishlist.store'), ['product_id' => $product->id])
                ->assertSessionHasErrors('product_id');
        }

        $this->assertDatabaseCount('wishlists', 0);
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_service_defensively_rejects_non_customer_accounts(): void
    {
        $admin = User::factory()->create([
            'account_type' => AccountType::Admin,
            'has_account' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'customer')
            ->post(route('shop.wishlist.store'), ['product_id' => $this->product()->id])
            ->assertForbidden();
    }

    public function test_wishlist_page_uses_live_localized_pricing_and_newest_first_order(): void
    {
        $this->setting('currency', 'default_currency', 'USD', 'string');
        $this->setting('tax', 'tax_mode', 'b2c', 'string');
        $tax = Tax::create(['name' => 'Tax', 'rate' => '10.0000', 'status' => true]);
        $this->setting('tax', 'default_tax_id', (string) $tax->id, 'integer');
        $customer = User::factory()->customer()->create();
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        $older = $this->product(['price' => '10.0000'], 'Older Product');
        $newer = $this->product(['price' => '20.0000'], 'Newest Product');
        $older->inventory()->create(['quantity' => 2, 'average_cost' => 1, 'low_stock_alert' => 1]);
        $newer->inventory()->create(['quantity' => 0, 'average_cost' => 1, 'low_stock_alert' => 1]);
        $wishlist->items()->create(['product_id' => $older->id, 'created_at' => now()->subHour()]);
        $wishlist->items()->create(['product_id' => $newer->id, 'created_at' => now()]);

        $this->actingAs($customer, 'customer')->get(route('shop.wishlist.index'))
            ->assertOk()
            ->assertSeeInOrder(['Newest Product', 'Older Product'])
            ->assertSee('$ 22.00')
            ->assertSee('$ 11.00')
            ->assertSee(__('shop.wishlist.available'))
            ->assertSee(__('shop.wishlist.unavailable'))
            ->assertViewHas('items', fn ($items) => $items->every(fn ($item) => $item->relationLoaded('product')
                && $item->product->relationLoaded('translations')
                && $item->product->relationLoaded('images')
                && $item->product->relationLoaded('inventory')));
    }

    public function test_configurable_availability_uses_active_variant_inventory(): void
    {
        $customer = User::factory()->customer()->create();
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        $parent = $this->product(['type' => ProductType::Configurable->value], 'Configurable Product');
        $variant = $this->product([
            'configurable_id' => $parent->id,
            'is_visible_individually' => false,
        ], 'Variant');
        $variant->inventory()->create(['quantity' => 3, 'average_cost' => 1, 'low_stock_alert' => 1]);
        $wishlist->items()->create(['product_id' => $parent->id]);

        $this->actingAs($customer, 'customer')->get(route('shop.wishlist.index'))
            ->assertOk()->assertSee(__('shop.wishlist.available'));

        $variant->update(['status' => false]);
        $this->get(route('shop.wishlist.index'))
            ->assertOk()->assertSee(__('shop.wishlist.unavailable'));
        $this->assertDatabaseHas('wishlist_items', ['product_id' => $parent->id]);
    }

    public function test_unavailable_saved_product_is_retained_and_product_deletion_cascades_item(): void
    {
        $customer = User::factory()->customer()->create();
        $wishlist = Wishlist::create(['user_id' => $customer->id]);
        $product = $this->product(['status' => false], 'Inactive Saved Product');
        $wishlist->items()->create(['product_id' => $product->id]);

        $this->actingAs($customer, 'customer')->get(route('shop.wishlist.index'))
            ->assertOk()
            ->assertSee('Inactive Saved Product')
            ->assertSee(__('shop.wishlist.unavailable'));
        $this->assertDatabaseHas('wishlist_items', ['product_id' => $product->id]);

        $product->delete();
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_product_cards_details_and_navbar_reflect_authenticated_wishlist_state(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->product([], 'Wishlisted Product');
        $product->inventory()->create(['quantity' => 3, 'average_cost' => 1, 'low_stock_alert' => 1]);
        Wishlist::create(['user_id' => $customer->id])->items()->create(['product_id' => $product->id]);

        $this->actingAs($customer, 'customer')->get(route('shop.home'))
            ->assertOk()
            ->assertSee(route('shop.wishlist.destroy', $product), false)
            ->assertViewHas('storefrontWishlistCount', 1);
        $this->get(route('shop.products.show', $product->translations->first()->url_key))
            ->assertOk()
            ->assertSee(route('shop.wishlist.destroy', $product), false)
            ->assertSee(__('shop.wishlist.remove'));
    }

    public function test_wishlist_operations_do_not_mutate_other_domains(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->product();
        $product->inventory()->create(['quantity' => 7, 'average_cost' => 2, 'low_stock_alert' => 1]);
        $beforeInventory = $product->inventory()->first()->toArray();
        $before = [
            'carts' => Cart::count(),
            'orders' => Order::count(),
            'movements' => $product->inventoryMovements()->count(),
        ];

        $this->actingAs($customer, 'customer')
            ->post(route('shop.wishlist.store'), ['product_id' => $product->id]);
        $this->delete(route('shop.wishlist.destroy', $product));

        $this->assertSame($beforeInventory, $product->inventory()->first()->toArray());
        $this->assertSame($before['carts'], Cart::count());
        $this->assertSame($before['orders'], Order::count());
        $this->assertSame($before['movements'], $product->inventoryMovements()->count());
    }

    private function product(array $state = [], ?string $name = null): Product
    {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => '10.0000',
            'use_default_tax' => true,
        ], $state));
        $product->translations()->create([
            'locale' => 'en',
            'name' => $name ?? 'Wishlist Product '.$product->id,
            'url_key' => 'wishlist-product-'.$product->id,
        ]);

        return $product;
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
        cache()->forget("setting.{$group}.{$key}");
    }
}
