<?php

namespace Tests\Feature\Orders;

use App\Enums\PaymentMethodType;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderCreationValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arbitrary_creation_token_and_inactive_customer_are_rejected(): void
    {
        [$admin, $customer, $product, $shipping, $payment] = $this->scenario();
        $data = $this->data($customer, $product, $shipping, $payment, str_repeat('a', 64));

        $this->actingAs($admin, 'admin')->post(route('admin.orders.store'), $data)
            ->assertSessionHasErrors('admin_creation_token');
        $this->assertDatabaseCount('orders', 0);

        $customer->update(['is_active' => false]);
        $page = $this->get(route('admin.orders.create'));
        $data['admin_creation_token'] = $this->token($page->getContent());
        $this->post(route('admin.orders.store'), $data)->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_zero_price_hidden_and_insufficient_stock_products_are_rejected(): void
    {
        [$admin, $customer, $product, $shipping, $payment] = $this->scenario();
        $this->actingAs($admin, 'admin');

        foreach ([
            ['special_price' => '0.0000', 'special_price_from' => now()->subMinute()],
            ['special_price' => null, 'special_price_from' => null, 'is_visible_individually' => false],
        ] as $changes) {
            $product->update($changes);
            $data = $this->data($customer, $product, $shipping, $payment, $this->newToken());
            $this->post(route('admin.orders.store'), $data)->assertSessionHasErrors('items');
            $this->assertDatabaseCount('orders', 0);
            $product->update(['special_price' => null, 'special_price_from' => null, 'is_visible_individually' => true]);
        }

        $product->inventory()->update(['quantity' => '0.0000']);
        $this->post(route('admin.orders.store'), $this->data(
            $customer, $product, $shipping, $payment, $this->newToken()
        ))->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_configurable_selection_must_match_one_authoritative_variant(): void
    {
        [$admin, $customer, , $shipping, $payment] = $this->scenario();
        $attribute = Attribute::factory()->create([
            'code' => 'color', 'type' => 'select', 'is_configurable' => true, 'is_active' => true,
        ]);
        $attribute->translations()->create(['locale' => 'en', 'admin_name' => 'Color']);
        $option = $attribute->options()->create(['code' => 'black', 'sort_order' => 1]);
        $option->translations()->create(['locale' => 'en', 'label' => 'Black']);
        $parent = Product::factory()->create([
            'type' => ProductType::Configurable->value, 'status' => true, 'is_visible_individually' => true,
        ]);
        $parent->translations()->create(['locale' => 'en', 'name' => 'Configured', 'url_key' => 'configured-admin']);
        $parent->superAttributes()->create(['attribute_id' => $attribute->id])->options()->sync([$option->id]);
        $variant = Product::factory()->create([
            'type' => ProductType::Simple->value, 'configurable_id' => $parent->id,
            'price' => '12.0000', 'status' => true, 'is_visible_individually' => false,
        ]);
        $variant->inventory()->create(['quantity' => 3, 'average_cost' => 1, 'low_stock_alert' => 1]);
        $variant->attributeValues()->create(['attribute_id' => $attribute->id, 'attribute_option_id' => $option->id]);
        $this->actingAs($admin, 'admin');
        $data = $this->data($customer, $variant, $shipping, $payment, $this->newToken());
        $data['items'][0] = [
            'product_id' => $variant->id,
            'parent_product_id' => $parent->id,
            'product_type' => 'configurable',
            'quantity' => 1,
            'options' => [$attribute->id => $option->id],
        ];

        $this->post(route('admin.orders.store'), $data)->assertRedirect();
        $order = $customer->orders()->firstOrFail();
        $this->assertDatabaseHas('order_item_options', [
            'order_item_id' => $order->items()->firstOrFail()->id,
            'attribute_code' => 'color',
            'option_code' => 'black',
        ]);

        $otherCustomer = User::factory()->customer()->create();
        $bad = $this->data($otherCustomer, $variant, $shipping, $payment, $this->newToken());
        $bad['items'][0] = $data['items'][0];
        $bad['items'][0]['options'][$attribute->id] = $option->id + 100;
        $this->post(route('admin.orders.store'), $bad)->assertSessionHasErrors('items.0.options');
        $this->assertDatabaseCount('orders', 1);
    }

    private function scenario(): array
    {
        foreach ([
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2b', 'string'],
            ['localization', 'default_locale', 'en', 'select'],
        ] as [$group, $key, $value, $type]) {
            Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
            cache()->forget("setting.{$group}.{$key}");
        }
        $admin = User::factory()->create();
        $customer = User::factory()->customer()->create();
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value, 'price' => '10.0000',
            'status' => true, 'is_visible_individually' => true,
        ]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Valid Product', 'url_key' => 'valid-admin-product']);
        $product->inventory()->create(['quantity' => 5, 'average_cost' => 1, 'low_stock_alert' => 1]);
        $shipping = ShippingMethod::query()->updateOrCreate(['code' => 'inside_beirut'], [
            'name' => 'Inside Beirut', 'type' => 'delivery', 'amount' => '2.0000',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $payment = PaymentMethod::query()->updateOrCreate(['code' => 'cash_on_delivery'], [
            'name' => 'Cash on Delivery', 'type' => PaymentMethodType::Offline,
            'is_active' => true, 'requires_payment_before_processing' => false, 'sort_order' => 1,
        ]);

        return [$admin, $customer, $product, $shipping, $payment];
    }

    private function data(User $customer, Product $product, ShippingMethod $shipping, PaymentMethod $payment, string $token): array
    {
        return [
            'customer_id' => $customer->id,
            'address_source' => 'manual',
            'manual_address' => [
                'first_name' => 'Test', 'last_name' => 'Customer', 'address_line_1' => 'Street',
                'city' => 'Beirut', 'country_code' => 'LB',
            ],
            'items' => [[
                'product_id' => $product->id, 'parent_product_id' => null,
                'product_type' => 'simple', 'quantity' => 1, 'options' => [],
            ]],
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'admin_creation_token' => $token,
        ];
    }

    private function newToken(): string
    {
        return $this->token($this->get(route('admin.orders.create'))->getContent());
    }

    private function token(string $content): string
    {
        preg_match('/name="admin_creation_token" value="([a-f0-9]{64})"/', $content, $matches);

        return $matches[1];
    }
}
