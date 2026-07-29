<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Services\CheckoutCartValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCartValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_hidden_and_insufficient_stock_products_return_stable_errors(): void
    {
        $cases = [
            [['status' => false], 1, 5, 'product_inactive'],
            [['is_visible_individually' => false], 1, 5, 'product_not_visible'],
            [[], 6, 5, 'insufficient_stock'],
        ];

        foreach ($cases as [$state, $quantity, $stock, $expectedCode]) {
            $cart = $this->cart();
            $product = $this->product($stock, $state);
            $this->item($cart, $product, $quantity);
            $result = $this->validator()->validate(
                $cart,
                $this->shippingMethod()->code,
                $this->paymentMethod()->code
            );

            $this->assertFalse($result->isValid());
            $this->assertSame($expectedCode, $result->errors[0]->code);
            $cart->delete();
        }
    }

    public function test_inactive_shipping_and_payment_methods_return_structured_errors(): void
    {
        $cart = $this->cart();
        $product = $this->product(5);
        $this->item($cart, $product, 1);
        $shipping = $this->shippingMethod(false);
        $payment = $this->paymentMethod(false);

        $result = $this->validator()->validate($cart, $shipping->code, $payment->code);

        $this->assertFalse($result->isValid());
        $this->assertSame(
            ['shipping_method_unavailable', 'payment_method_unavailable'],
            collect($result->errors)->pluck('code')->all()
        );
    }

    public function test_defensive_missing_product_and_zero_quantity_paths_return_data(): void
    {
        $shipping = $this->shippingMethod();
        $payment = $this->paymentMethod();
        $missing = new CartItem([
            'product_id' => 999,
            'product_type' => CartItemType::Simple,
            'quantity' => 1,
        ]);
        $missing->setRelation('product', null);
        $missingResult = $this->validator()->validateLoadedItems(
            collect([$missing]),
            $shipping,
            $payment
        );
        $product = $this->product(5);
        $zero = new CartItem([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'quantity' => 0,
        ]);
        $zero->setRelation('product', $product);
        $zeroResult = $this->validator()->validateLoadedItems(
            collect([$zero]),
            $shipping,
            $payment
        );

        $this->assertSame('product_unavailable', $missingResult->errors[0]->code);
        $this->assertSame('invalid_quantity', $zeroResult->errors[0]->code);
    }

    public function test_product_deletion_cascades_item_and_empty_cart_is_rejected(): void
    {
        $cart = $this->cart();
        $product = $this->product(5);
        $item = $this->item($cart, $product, 1);
        $shipping = $this->shippingMethod();
        $payment = $this->paymentMethod();

        $product->delete();

        $this->assertModelMissing($item);
        $result = $this->validator()->validate($cart, $shipping->code, $payment->code);
        $this->assertSame('empty_cart', $result->errors[0]->code);
    }

    public function test_database_rejects_non_positive_persisted_quantities(): void
    {
        $cart = $this->cart();
        $product = $this->product(5);

        foreach ([0, -1] as $quantity) {
            try {
                $this->item($cart, $product, $quantity);
                $this->fail('A non-positive Cart quantity was persisted.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_configurable_variant_uses_parent_visibility_and_normalizes_options(): void
    {
        [$parent, $variant] = $this->configuredProduct();
        $cart = $this->cart();
        $this->item($cart, $variant, 2, CartItemType::Configurable);

        $result = $this->validator()->validate(
            $cart,
            $this->shippingMethod()->code,
            $this->paymentMethod()->code
        );

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->items[0]->product->is($variant));
        $this->assertTrue($result->items[0]->displayProduct->is($parent));
        $this->assertSame([[
            'attribute_code' => 'color',
            'attribute_name' => 'Color',
            'option_code' => 'black',
            'option_label' => 'Black',
        ]], $result->items[0]->optionSnapshots);
    }

    public function test_incomplete_configurable_values_are_rejected(): void
    {
        [, $variant] = $this->configuredProduct();
        $variant->attributeValues()->delete();
        $cart = $this->cart();
        $this->item($cart, $variant, 1, CartItemType::Configurable);

        $result = $this->validator()->validate(
            $cart,
            $this->shippingMethod()->code,
            $this->paymentMethod()->code
        );

        $this->assertSame('invalid_configuration', $result->errors[0]->code);
    }

    private function validator(): CheckoutCartValidator
    {
        return app(CheckoutCartValidator::class);
    }

    private function cart(): Cart
    {
        return Cart::create([
            'guest_token_hash' => hash('sha256', fake()->unique()->uuid()),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    private function product(float $stock, array $state = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'status' => true,
            'is_visible_individually' => true,
            'price' => 100,
        ], $state));
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Checkout Product',
            'url_key' => 'checkout-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => $stock,
            'average_cost' => 10,
            'low_stock_alert' => 1,
        ]);

        return $product;
    }

    private function item(
        Cart $cart,
        Product $product,
        int $quantity,
        CartItemType $type = CartItemType::Simple
    ): CartItem {
        return $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => $type,
            'configuration_hash' => hash('sha256', $type->value.'-'.$product->id),
            'quantity' => $quantity,
        ]);
    }

    private function shippingMethod(bool $active = true): ShippingMethod
    {
        return ShippingMethod::factory()->create(['is_active' => $active]);
    }

    private function paymentMethod(bool $active = true): PaymentMethod
    {
        return PaymentMethod::factory()->create(['is_active' => $active]);
    }

    private function configuredProduct(): array
    {
        $attribute = Attribute::factory()->create([
            'code' => 'color',
            'type' => 'select',
            'is_configurable' => true,
            'is_active' => true,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);
        $option = $attribute->options()->create(['code' => 'black', 'sort_order' => 1]);
        $option->translations()->create(['locale' => 'en', 'label' => 'Black']);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $parent->translations()->create([
            'locale' => 'en',
            'name' => 'Configured Product',
            'url_key' => 'configured-'.$parent->id,
        ]);
        $parent->superAttributes()->create([
            'attribute_id' => $attribute->id,
        ])->options()->sync([$option->id]);
        $variant = $this->product(5, [
            'configurable_id' => $parent->id,
            'is_visible_individually' => false,
        ]);
        $variant->attributeValues()->create([
            'attribute_id' => $attribute->id,
            'attribute_option_id' => $option->id,
        ]);

        return [$parent, $variant];
    }
}
