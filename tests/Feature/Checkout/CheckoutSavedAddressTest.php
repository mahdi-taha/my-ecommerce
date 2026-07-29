<?php

namespace Tests\Feature\Checkout;

use App\Enums\CartItemType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\CheckoutOrderPlacementService;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CheckoutSavedAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_lists_addresses_in_approved_order_and_preselects_default_shipping(): void
    {
        [$cart, $customer] = $this->scenario();
        $older = CustomerAddress::factory()->for($customer, 'customer')->create([
            'label' => 'Older',
            'created_at' => now()->subDay(),
        ]);
        $default = CustomerAddress::factory()->for($customer, 'customer')->create([
            'label' => 'Default Home',
            'is_default_shipping' => true,
        ]);

        $response = $this->actingAs($customer, 'customer')->get(route('shop.checkout.show'));

        $response->assertOk()
            ->assertSee('Default Home')
            ->assertSee('Older')
            ->assertSee('value="'.$default->id.'"', false)
            ->assertSee('id="address_source_saved" value="saved" checked', false);
        $this->assertLessThan(
            strpos($response->getContent(), 'Older'),
            strpos($response->getContent(), 'Default Home')
        );
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
        $this->assertNotSame($older->id, $default->id);
    }

    public function test_saved_address_checkout_creates_independent_immutable_snapshots(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        $address = CustomerAddress::factory()->for($customer, 'customer')->create([
            'first_name' => 'Saved',
            'last_name' => 'Recipient',
            'address_line_1' => 'Original Street',
            'is_default_shipping' => true,
        ]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->savedData($shipping, $payment, $address),
            $customer
        );

        $this->assertTrue($result->successful);
        $billing = $result->order->billingAddress;
        $shippingAddress = $result->order->shippingAddress;
        $this->assertNotSame($billing->id, $shippingAddress->id);
        $this->assertSame('Original Street', $billing->address_line_1);
        $this->assertSame($billing->only($this->snapshotFields()), $shippingAddress->only($this->snapshotFields()));
        $this->assertFalse(Schema::hasColumn('orders', 'customer_address_id'));
        $this->assertFalse(Schema::hasColumn('order_addresses', 'customer_address_id'));

        $address->update(['address_line_1' => 'Changed Street']);
        $address->delete();
        $this->assertSame('Original Street', $billing->fresh()->address_line_1);
        $this->assertSame('Original Street', $shippingAddress->fresh()->address_line_1);
    }

    public function test_authenticated_manual_checkout_does_not_save_address_when_disabled(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->manualData($shipping, $payment),
            $customer
        );

        $this->assertTrue($result->successful);
        $this->assertDatabaseCount('customer_addresses', 0);
        $this->assertCount(2, $result->order->addresses);
    }

    public static function defaultSelections(): array
    {
        return [
            'shipping' => [true, false],
            'billing' => [false, true],
            'both' => [true, true],
        ];
    }

    #[DataProvider('defaultSelections')]
    public function test_manual_address_can_be_saved_with_requested_defaults(
        bool $defaultShipping,
        bool $defaultBilling
    ): void {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        $data = $this->manualData($shipping, $payment) + [
            'save_address' => true,
            'make_default_shipping' => $defaultShipping,
            'make_default_billing' => $defaultBilling,
        ];

        $result = app(CheckoutOrderPlacementService::class)->place($cart, $data, $customer);

        $this->assertTrue($result->successful);
        $saved = $customer->customerAddresses()->where('label', 'Checkout Address')->firstOrFail();
        $this->assertSame($defaultShipping, $saved->is_default_shipping);
        $this->assertSame($defaultBilling, $saved->is_default_billing);
        $this->assertSame((int) $defaultShipping, $customer->customerAddresses()->where('is_default_shipping', true)->count());
        $this->assertSame((int) $defaultBilling, $customer->customerAddresses()->where('is_default_billing', true)->count());
    }

    public function test_request_enforces_exactly_one_source_and_safe_saved_address_ownership(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        $foreign = CustomerAddress::factory()->create();
        $base = $this->manualData($shipping, $payment);

        $this->actingAs($customer, 'customer')
            ->post(route('shop.checkout.store'), array_merge($base, [
                'address_source' => 'saved',
                'saved_address_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors(['saved_address_id', 'manual_address']);
        $foreignError = session('errors')->get('saved_address_id');

        $this->post(route('shop.checkout.store'), array_merge($base, [
            'address_source' => 'saved',
            'saved_address_id' => 999999,
            'manual_address' => [],
        ]))->assertSessionHasErrors('saved_address_id');
        $this->assertSame($foreignError, session('errors')->get('saved_address_id'));

        $this->post(route('shop.checkout.store'), array_merge($base, [
            'address_source' => null,
            'manual_address' => [],
        ]))->assertSessionHasErrors('address_source');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_guest_must_use_manual_address_and_can_still_place_order(): void
    {
        [$cart, , $shipping, $payment, $token] = $this->scenario(guest: true);
        $foreign = CustomerAddress::factory()->create();
        $saved = $this->manualData($shipping, $payment) + [
            'address_source' => 'saved',
            'saved_address_id' => $foreign->id,
        ];
        unset($saved['manual_address']);

        $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->post(route('shop.checkout.store'), $saved)
            ->assertSessionHasErrors('saved_address_id');

        $response = $this->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->post(route('shop.checkout.store'), $this->manualData($shipping, $payment));

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('shop.checkout.success', $order));
        $this->assertNull($order->user_id);
    }

    public function test_save_and_default_flags_are_rejected_outside_authenticated_manual_flow(): void
    {
        [, $customer, $shipping, $payment] = $this->scenario();
        $address = CustomerAddress::factory()->for($customer, 'customer')->create();
        $saved = $this->savedData($shipping, $payment, $address) + [
            'save_address' => true,
            'make_default_shipping' => true,
            'make_default_billing' => true,
        ];

        $this->actingAs($customer, 'customer')
            ->post(route('shop.checkout.store'), $saved)
            ->assertSessionHasErrors([
                'save_address',
                'make_default_shipping',
                'make_default_billing',
            ]);

        auth('customer')->logout();
        $this->post(route('shop.checkout.store'), $this->manualData($shipping, $payment) + [
            'save_address' => true,
        ])->assertSessionHasErrors('save_address');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_customer_is_rejected_after_transactional_revalidation(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        $customer->update(['is_active' => false]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->manualData($shipping, $payment),
            $customer
        );

        $this->assertSame(['customer_unavailable'], $result->failureCodes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_order_failure_rolls_back_saved_address_and_default_changes(): void
    {
        [$cart, $customer, $shipping, $payment] = $this->scenario();
        $original = CustomerAddress::factory()->for($customer, 'customer')->create([
            'is_default_shipping' => true,
            'is_default_billing' => true,
        ]);
        Event::listen('eloquent.created: '.Order::class, function (): never {
            throw new RuntimeException('Injected Order failure.');
        });
        $data = $this->manualData($shipping, $payment) + [
            'save_address' => true,
            'make_default_shipping' => true,
            'make_default_billing' => true,
        ];

        try {
            app(CheckoutOrderPlacementService::class)->place($cart, $data, $customer);
            $this->fail('The injected failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected Order failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('customer_addresses', 1);
        $this->assertTrue($original->fresh()->is_default_shipping);
        $this->assertTrue($original->fresh()->is_default_billing);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    private function scenario(bool $guest = false): array
    {
        foreach ([
            ['checkout', 'allow_guest_checkout', '1', 'boolean'],
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2b', 'string'],
            ['cart', 'lifetime_days', '30', 'integer'],
        ] as [$group, $key, $value, $type]) {
            Setting::query()->updateOrCreate(compact('group', 'key'), compact('value', 'type'));
            cache()->forget("setting.{$group}.{$key}");
        }

        $customer = User::factory()->customer()->create();
        $token = str_repeat('d', 64);
        $cart = Cart::create([
            'user_id' => $guest ? null : $customer->id,
            'guest_token_hash' => $guest ? hash('sha256', $token) : null,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'price' => '25.0000',
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en',
            'name' => 'Address Product',
            'url_key' => 'address-product-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000',
            'average_cost' => '5.0000',
            'low_stock_alert' => '1.0000',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);
        $shipping = ShippingMethod::factory()->create(['amount' => '2.0000', 'is_active' => true]);
        $payment = PaymentMethod::factory()->create(['is_active' => true]);

        return [$cart, $customer, $shipping, $payment, $token];
    }

    private function manualData(ShippingMethod $shipping, PaymentMethod $payment): array
    {
        return [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => $this->customerData(),
            'address_source' => 'manual',
            'manual_address' => [
                'label' => 'Checkout Address',
                'first_name' => 'Checkout',
                'last_name' => 'Customer',
                'company' => null,
                'email' => 'checkout@example.com',
                'phone' => '70123456',
                'address_line_1' => 'Checkout Street',
                'address_line_2' => null,
                'city' => 'Beirut',
                'state' => 'Beirut',
                'postal_code' => null,
                'country_code' => 'LB',
            ],
        ];
    }

    private function savedData(
        ShippingMethod $shipping,
        PaymentMethod $payment,
        CustomerAddress $address
    ): array {
        return [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => $this->customerData(),
            'address_source' => 'saved',
            'saved_address_id' => $address->id,
        ];
    }

    private function customerData(): array
    {
        return [
            'first_name' => 'Checkout',
            'last_name' => 'Customer',
            'phone' => '70123456',
            'email' => 'checkout@example.com',
        ];
    }

    private function snapshotFields(): array
    {
        return [
            'first_name', 'last_name', 'company', 'email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'state',
            'postal_code', 'country_code',
        ];
    }
}
