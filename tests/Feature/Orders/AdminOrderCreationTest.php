<?php

namespace Tests\Feature\Orders;

use App\Enums\PaymentMethodType;
use App\Enums\ProductType;
use App\Events\CommerceEventOccurred;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_registered_customer_order_from_authoritative_values(): void
    {
        [$admin, $customer, $product, $shipping, $payment] = $this->scenario();
        $quantity = $product->inventory->quantity;
        $page = $this->actingAs($admin, 'admin')->get(route('admin.orders.create'))->assertOk();
        $token = $this->creationToken($page->getContent());
        $data = $this->orderData($customer, $product, $shipping, $payment, $token) + [
            'subtotal' => '0.0000',
            'grand_total' => '0.0000',
        ];

        $this->postJson(route('admin.orders.summary'), $data)
            ->assertOk()
            ->assertJsonPath('summary.subtotal', '20.0000')
            ->assertJsonPath('summary.tax_total', '2.0000')
            ->assertJsonPath('summary.grand_total', '25.0000');
        $this->assertDatabaseCount('orders', 0);
        Event::fake([CommerceEventOccurred::class]);

        $response = $this->post(route('admin.orders.store'), $data);
        $order = $customer->orders()->firstOrFail();

        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame(hash('sha256', $token), $order->admin_creation_key);
        $this->assertSame('ar', $order->locale);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('unfulfilled', $order->fulfillment_status);
        $this->assertSame('20.0000', $order->subtotal);
        $this->assertSame('2.0000', $order->tax_total);
        $this->assertSame('3.0000', $order->shipping_total);
        $this->assertSame('25.0000', $order->grand_total);
        $this->assertSame('25.0000', $order->payment()->firstOrFail()->amount);
        $this->assertFalse($order->requires_payment_before_processing);
        $this->assertCount(2, $order->addresses);
        $this->assertSame($shipping->code, $order->shipping()->firstOrFail()->shipping_method_code);
        $this->assertSame($quantity, $product->inventory()->firstOrFail()->quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => 'pending',
            'created_by' => $admin->id,
        ]);
        Event::assertDispatched(CommerceEventOccurred::class, fn (CommerceEventOccurred $event): bool => $event->entityType === 'order' && $event->entityId === $order->id
        );

        $this->post(route('admin.orders.store'), $data)
            ->assertRedirect(route('admin.orders.show', $order));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_admin_creates_manual_customer_order_from_owned_saved_address(): void
    {
        [$admin, , $product, $shipping, $payment] = $this->scenario();
        $customer = User::factory()->manualCustomer()->create(['is_active' => true]);
        $address = CustomerAddress::factory()->create([
            'user_id' => $customer->id,
            'address_line_1' => 'Saved Admin Address',
            'country_code' => 'LB',
        ]);
        $page = $this->actingAs($admin, 'admin')->get(route('admin.orders.create'));
        $token = $this->creationToken($page->getContent());
        $data = $this->orderData($customer, $product, $shipping, $payment, $token);
        $data['address_source'] = 'saved';
        $data['saved_address_id'] = $address->id;
        unset($data['manual_address']);

        $this->post(route('admin.orders.store'), $data)->assertRedirect();
        $order = $customer->orders()->firstOrFail();

        $this->assertNull($order->customer_email);
        $this->assertSame('Saved Admin Address', $order->billingAddress()->firstOrFail()->address_line_1);
        $this->assertSame('Saved Admin Address', $order->shippingAddress()->firstOrFail()->address_line_1);
        $this->assertDatabaseCount('customer_addresses', 1);
    }

    public function test_create_page_and_lookups_are_admin_only_and_bounded_to_eligible_records(): void
    {
        [$admin, $customer, $product] = $this->scenario();
        User::factory()->customer()->inactive()->create(['name' => 'Hidden Customer']);
        Product::factory()->create(['status' => false, 'sku' => 'HIDDEN-PRODUCT']);

        $this->get(route('admin.orders.create'))->assertRedirect(route('admin.login'));
        $this->actingAs($admin, 'admin')
            ->getJson(route('admin.orders.lookups.customers', ['q' => $customer->name]))
            ->assertOk()
            ->assertJsonPath('results.0.id', $customer->id);
        $this->getJson(route('admin.orders.lookups.products', ['q' => $product->sku]))
            ->assertOk()
            ->assertJsonPath('results.0.id', $product->id);
    }

    private function scenario(): array
    {
        $this->setting('currency', 'default_currency', 'USD', 'string');
        $this->setting('tax', 'tax_mode', 'b2b', 'string');
        $this->setting('localization', 'default_locale', 'ar', 'select');
        $tax = Tax::create(['name' => 'Default Tax', 'rate' => '10.0000', 'status' => true]);
        $this->setting('tax', 'default_tax_id', (string) $tax->id, 'integer');
        $admin = User::factory()->create();
        $customer = User::factory()->customer()->create(['phone' => '70123456']);
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'price' => '10.0000',
            'use_default_tax' => true,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        foreach (['en' => 'Admin Product', 'ar' => 'منتج إداري'] as $locale => $name) {
            $product->translations()->create([
                'locale' => $locale,
                'name' => $name,
                'url_key' => "admin-product-{$locale}-{$product->id}",
            ]);
        }
        $product->inventory()->create([
            'quantity' => '10.0000', 'average_cost' => '2.0000', 'low_stock_alert' => '1.0000',
        ]);
        $shipping = ShippingMethod::query()->updateOrCreate(['code' => 'inside_beirut'], [
            'name' => 'Inside Beirut', 'type' => 'delivery', 'amount' => '3.0000',
            'is_active' => true, 'sort_order' => 1,
        ]);
        $payment = PaymentMethod::query()->updateOrCreate(['code' => 'cash_on_delivery'], [
            'name' => 'Cash on Delivery',
            'type' => PaymentMethodType::Offline,
            'is_active' => true,
            'requires_payment_before_processing' => false,
            'sort_order' => 1,
        ]);

        return [$admin, $customer, $product->load('inventory'), $shipping, $payment];
    }

    private function orderData(User $customer, Product $product, ShippingMethod $shipping, PaymentMethod $payment, string $token): array
    {
        return [
            'customer_id' => $customer->id,
            'address_source' => 'manual',
            'manual_address' => [
                'first_name' => 'Order', 'last_name' => 'Recipient', 'company' => null,
                'email' => $customer->email, 'phone' => '70123456',
                'address_line_1' => 'Admin Street', 'address_line_2' => null,
                'city' => 'Beirut', 'state' => null, 'postal_code' => null, 'country_code' => 'lb',
            ],
            'items' => [[
                'product_id' => $product->id,
                'parent_product_id' => null,
                'product_type' => 'simple',
                'quantity' => 2,
                'options' => [],
            ]],
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'admin_creation_token' => $token,
        ];
    }

    private function creationToken(string $content): string
    {
        preg_match('/name="admin_creation_token" value="([a-f0-9]{64})"/', $content, $matches);
        $this->assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    private function setting(string $group, string $key, string $value, string $type): void
    {
        Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
        cache()->forget("setting.{$group}.{$key}");
    }
}
