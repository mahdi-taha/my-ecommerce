<?php

namespace Tests\Feature\Storefront;

use App\Enums\AccountType;
use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ConfigurableCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_configuration_adds_selected_variant_and_merges_identical_variant(): void
    {
        [$parent, $color, $red, $blue] = $this->configuredProduct();
        $redVariant = $this->variant($parent, [$color->id => $red->id], 5);
        $this->variant($parent, [$color->id => $blue->id], 5);
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        foreach ([2, 3] as $quantity) {
            $this->post(route('shop.cart.items.store'), [
                'product_type' => CartItemType::Configurable->value,
                'product_id' => $parent->id,
                'options' => [$color->id => $red->id],
                'quantity' => $quantity,
                'variant_id' => PHP_INT_MAX,
            ])->assertRedirect(route('shop.cart.index'));
        }

        $item = $customer->cart->items()->sole();
        $this->assertSame($redVariant->id, $item->product_id);
        $this->assertSame(CartItemType::Configurable, $item->product_type);
        $this->assertSame('5.0000', $item->quantity);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_different_selected_variants_remain_separate(): void
    {
        [$parent, $color, $red, $blue] = $this->configuredProduct();
        $redVariant = $this->variant($parent, [$color->id => $red->id], 5);
        $blueVariant = $this->variant($parent, [$color->id => $blue->id], 5);
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        foreach ([$red, $blue] as $option) {
            $this->add($parent, [$color->id => $option->id], 1);
        }

        $this->assertEqualsCanonicalizing(
            [$redVariant->id, $blueVariant->id],
            $customer->cart->items()->pluck('product_id')->all()
        );
    }

    public function test_incomplete_extra_invalid_and_unavailable_combinations_are_rejected(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $size = $this->attribute('size', 'Size');
        $large = $this->option($size, 'large', 'Large');
        $parent->superAttributes()->create([
            'attribute_id' => $size->id,
        ])->options()->sync([$large->id]);
        $this->variant($parent, [
            $color->id => $red->id,
            $size->id => $large->id,
        ], 5);
        $otherAttribute = $this->attribute('material', 'Material');
        $cotton = $this->option($otherAttribute, 'cotton', 'Cotton');
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $invalidSelections = [
            [$color->id => $red->id],
            [$color->id => $red->id, $size->id => $large->id, $otherAttribute->id => $cotton->id],
            [$color->id => $large->id, $size->id => $large->id],
        ];

        foreach ($invalidSelections as $options) {
            $this->add($parent, $options, 1)->assertSessionHasErrors();
        }

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_inactive_parent_variant_and_out_of_stock_variant_are_rejected(): void
    {
        [$parent, $color, $red, $blue] = $this->configuredProduct();
        $redVariant = $this->variant($parent, [$color->id => $red->id], 5);
        $this->variant($parent, [$color->id => $blue->id], 0);
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');

        $this->add($parent, [$color->id => $blue->id], 1)
            ->assertSessionHasErrors('quantity');

        $redVariant->update(['status' => false]);
        $this->add($parent, [$color->id => $red->id], 1)
            ->assertSessionHasErrors('options');

        $redVariant->update(['status' => true]);
        $parent->update(['status' => false]);
        $this->add($parent, [$color->id => $red->id], 1)
            ->assertSessionHasErrors('product_id');

        $parent->update([
            'status' => true,
            'is_visible_individually' => false,
        ]);
        $this->add($parent, [$color->id => $red->id], 1)
            ->assertSessionHasErrors('product_id');

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_renders_parent_name_selected_options_and_variant_commerce_data(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $variant = $this->variant($parent, [$color->id => $red->id], 5);
        $variant->update(['sku' => 'SHIRT-RED', 'price' => 42]);
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->add($parent, [$color->id => $red->id], 1);

        $this->get(route('shop.cart.index'))
            ->assertOk()
            ->assertSee('Configured Shirt')
            ->assertSee('Color: Red')
            ->assertSee('SHIRT-RED')
            ->assertSee('$ 42.00');
    }

    public function test_update_and_guest_merge_revalidate_configurable_variant_stock(): void
    {
        [$parent, $color, $red] = $this->configuredProduct();
        $variant = $this->variant($parent, [$color->id => $red->id], 5);
        $tokens = app(GuestCartTokenService::class);
        $rawToken = $tokens->generate();
        $customer = $this->customer();
        $configurationHash = hash('sha256', json_encode([
            'type' => CartItemType::Configurable->value,
            'product_id' => $variant->id,
        ], JSON_THROW_ON_ERROR));
        $customerCart = $this->cart(['user_id' => $customer->id]);
        $guestCart = $this->cart([
            'guest_token_hash' => $tokens->hash($rawToken),
        ]);

        foreach ([[$customerCart, 3], [$guestCart, 4]] as [$cart, $quantity]) {
            $cart->items()->create([
                'product_id' => $variant->id,
                'product_type' => CartItemType::Configurable->value,
                'configuration_hash' => $configurationHash,
                'quantity' => $quantity,
            ]);
        }

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $rawToken)
            ->post(route('customer.login.store'), [
                'email' => $customer->email,
                'password' => 'password',
            ]);

        $response->assertRedirect()->assertSessionHas('warning');
        $this->assertSame($customerCart->id, $customer->cart->fresh()->id);
        $this->assertSame('5.0000', $customer->cart->items()->sole()->quantity);
        $this->assertModelMissing($guestCart);

        $this->actingAs($customer, 'customer')
            ->patch(
                route('shop.cart.items.update', $customer->cart->items()->sole()),
                ['quantity' => 6]
            )
            ->assertSessionHasErrors('quantity');
    }

    private function add(Product $parent, array $options, int $quantity)
    {
        return $this->post(route('shop.cart.items.store'), [
            'product_type' => CartItemType::Configurable->value,
            'product_id' => $parent->id,
            'options' => $options,
            'quantity' => $quantity,
        ]);
    }

    private function configuredProduct(): array
    {
        $color = $this->attribute('color', 'Color');
        $red = $this->option($color, 'red', 'Red');
        $blue = $this->option($color, 'blue', 'Blue');
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'sku' => 'SHIRT',
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => 'Configured Shirt',
            'url_key' => 'configured-shirt',
        ]);
        $parent->superAttributes()->create([
            'attribute_id' => $color->id,
        ])->options()->sync([$red->id, $blue->id]);

        return [$parent, $color, $red, $blue];
    }

    private function attribute(string $code, string $label): Attribute
    {
        $attribute = Attribute::factory()->create([
            'code' => $code,
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $attribute->translations()->create([
            'locale' => 'en',
            'admin_name' => $label,
        ]);

        return $attribute;
    }

    private function option(
        Attribute $attribute,
        string $code,
        string $label
    ): AttributeOption {
        $option = $attribute->options()->create([
            'code' => $code,
            'sort_order' => $attribute->options()->count(),
        ]);
        $option->translations()->create([
            'locale' => 'en',
            'label' => $label,
        ]);

        return $option;
    }

    private function variant(
        Product $parent,
        array $options,
        int $stock
    ): Product {
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => $parent->id,
            'status' => true,
            'is_visible_individually' => false,
            'price' => 100,
        ]);

        foreach ($options as $attributeId => $optionId) {
            $variant->attributeValues()->create([
                'attribute_id' => $attributeId,
                'attribute_option_id' => $optionId,
            ]);
        }

        $variant->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 20,
            'low_stock_alert' => 1,
        ]);

        return $variant;
    }

    private function customer(): User
    {
        return User::factory()->create([
            'account_type' => AccountType::Customer,
            'has_account' => true,
            'is_active' => true,
            'password' => Hash::make('password'),
        ]);
    }

    private function cart(array $owner): Cart
    {
        return Cart::create(array_merge($owner, [
            'last_activity_at' => now()->subHour(),
            'expires_at' => now()->addDays(29),
        ]));
    }
}
