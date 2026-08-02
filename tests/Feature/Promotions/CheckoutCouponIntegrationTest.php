<?php

namespace Tests\Feature\Promotions;

use App\Enums\CartItemType;
use App\Enums\CouponType;
use App\Enums\ProductType;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\Tax;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutAddressResolver;
use App\Services\CheckoutCartValidator;
use App\Services\CheckoutOrderPlacementService;
use App\Services\CheckoutService;
use App\Services\CouponCartService;
use App\Services\CouponEligibilityService;
use App\Services\CouponUsageService;
use App\Services\GuestCartTokenService;
use App\Services\OrderService;
use App\Services\OrderSnapshotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CheckoutCouponIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_and_relationships_support_coupon_checkout_snapshots(): void
    {
        $this->assertTrue(Schema::hasColumn('carts', 'coupon_id'));
        $this->assertTrue(Schema::hasColumn('order_items', 'discount_amount'));

        [$cart] = $this->scenario();
        $coupon = Coupon::factory()->create(['is_active' => true]);
        $cart->update(['coupon_id' => $coupon->id]);

        $this->assertTrue($cart->fresh()->coupon->is($coupon));
        $coupon->delete();
        $this->assertNull($cart->fresh()->coupon_id);
    }

    public function test_apply_replace_and_remove_return_authoritative_coupon_summary(): void
    {
        [$cart, , $customer, $shipping, $payment] = $this->scenario();
        $fixed = Coupon::factory()->create([
            'code' => 'FIXED10', 'is_active' => true, 'value' => '10.0000',
        ]);
        $percentage = Coupon::factory()->create([
            'code' => 'PERCENT20', 'is_active' => true,
            'type' => CouponType::Percentage, 'value' => '20.0000',
        ]);

        $payload = ['shipping_method' => $shipping->code, 'payment_method' => $payment->code];
        $this->actingAs($customer, 'customer')->postJson(
            route('shop.checkout.coupon.store'),
            $payload + ['coupon_code' => ' fixed10 ', 'shipping_amount' => '9999.0000']
        )->assertOk()
            ->assertJsonPath('summary.discount_total', '10.0000')
            ->assertJsonPath('summary.shipping_amount', '5.0000')
            ->assertJsonPath('summary.grand_total', '104.0000');
        $this->assertSame($fixed->id, $cart->fresh()->coupon_id);

        $this->postJson(route('shop.checkout.coupon.store'), $payload + [
            'coupon_code' => $percentage->code,
        ])->assertOk()->assertJsonPath('summary.discount_total', '20.0000');
        $this->assertSame($percentage->id, $cart->fresh()->coupon_id);

        $this->deleteJson(route('shop.checkout.coupon.destroy'), $payload)
            ->assertOk()
            ->assertJsonPath('summary.discount_total', '0.0000');
        $this->assertNull($cart->fresh()->coupon_id);
    }

    public function test_coupon_is_applied_before_tax_and_shipping_is_not_discounted(): void
    {
        [$cart, , , $shipping, $payment] = $this->scenario();
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'type' => CouponType::Percentage,
            'value' => '20.0000',
        ]);
        $cart->update(['coupon_id' => $coupon->id]);

        $summary = app(CheckoutService::class)->summarize($cart, $shipping->code, $payment->code);

        $this->assertSame('100.0000', $summary->subtotal);
        $this->assertSame('20.0000', $summary->discountTotal);
        $this->assertSame('8.0000', $summary->taxTotal);
        $this->assertSame('5.0000', $summary->shippingAmount);
        $this->assertSame('93.0000', $summary->grandTotal);
        $this->assertSame('20.0000', $summary->items[0]['discount_amount']);
        $this->assertSame('88.0000', $summary->items[0]['row_total']);
    }

    public function test_placement_creates_immutable_usage_and_discount_snapshots_and_clears_coupon(): void
    {
        [$cart, , $customer, $shipping, $payment] = $this->scenario();
        $coupon = Coupon::factory()->create([
            'code' => 'PLACE10', 'is_active' => true, 'value' => '10.0000',
        ]);
        $cart->update(['coupon_id' => $coupon->id]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertTrue($result->successful);
        $order = $result->order;
        $this->assertSame('10.0000', $order->discount_total);
        $this->assertSame('9.0000', $order->tax_total);
        $this->assertSame('104.0000', $order->grand_total);
        $this->assertSame('10.0000', $order->items->first()->discount_amount);
        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'coupon_code' => 'PLACE10',
            'eligible_subtotal' => '100.0000',
            'discount_amount' => '10.0000',
        ]);
        $this->assertNull($cart->fresh()->coupon_id);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_invalid_applied_coupon_is_removed_and_placement_requires_resubmission(): void
    {
        [$cart, , $customer, $shipping, $payment] = $this->scenario();
        $coupon = Coupon::factory()->create(['is_active' => true]);
        $cart->update(['coupon_id' => $coupon->id]);
        $coupon->update(['is_active' => false]);

        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->assertFalse($result->successful);
        $this->assertSame(['coupon_invalid'], $result->failureCodes());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertNull($cart->fresh()->coupon_id);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_expected_coupon_eligibility_failures_are_returned_without_replacing_cart_coupon(): void
    {
        [$cart, , $customer] = $this->scenario();
        $current = Coupon::factory()->create(['is_active' => true]);
        $cart->update(['coupon_id' => $current->id]);
        $service = app(CouponCartService::class);
        $invalid = [
            Coupon::factory()->create(['is_active' => false]),
            Coupon::factory()->create(['is_active' => true, 'ends_at' => now()->subMinute()]),
            Coupon::factory()->create(['is_active' => true, 'minimum_subtotal' => '101.0000']),
        ];

        foreach ($invalid as $coupon) {
            try {
                $service->apply($cart, $coupon->code, '100.0000', $customer);
                $this->fail('An ineligible Coupon was applied.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('coupon_code', $exception->errors());
            }

            $this->assertSame($current->id, $cart->fresh()->coupon_id);
        }
    }

    public function test_guest_restrictions_and_unknown_codes_are_rejected(): void
    {
        [$cart] = $this->scenario();
        $cart->update(['user_id' => null, 'guest_token_hash' => hash('sha256', 'guest')]);
        $service = app(CouponCartService::class);
        $restricted = Coupon::factory()->create([
            'is_active' => true,
            'per_customer_usage_limit' => 1,
        ]);

        foreach ([$restricted->code, 'UNKNOWN'] as $code) {
            try {
                $service->apply($cart, $code, '100.0000', null);
                $this->fail('The guest Coupon was unexpectedly accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('coupon_code', $exception->errors());
            }
        }
    }

    public function test_guest_merge_preserves_customer_coupon_or_transfers_and_revalidates_guest_coupon(): void
    {
        [$customerCart, $product, $customer] = $this->scenario();
        $customerCoupon = Coupon::factory()->create(['is_active' => true]);
        $guestCoupon = Coupon::factory()->create(['is_active' => true]);
        $customerCart->update(['coupon_id' => $customerCoupon->id]);
        $token = str_repeat('a', 64);
        $guestCart = Cart::create([
            'guest_token_hash' => app(GuestCartTokenService::class)->hash($token),
            'coupon_id' => $guestCoupon->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $guestCart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);

        app(CartService::class)->mergeGuestCart($customer, $token);

        $this->assertSame($customerCoupon->id, $customerCart->fresh()->coupon_id);
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
    }

    public function test_order_item_discount_snapshot_is_immutable(): void
    {
        [$cart, , $customer, $shipping, $payment] = $this->scenario();
        $coupon = Coupon::factory()->create(['is_active' => true]);
        $cart->update(['coupon_id' => $coupon->id]);
        $result = app(CheckoutOrderPlacementService::class)->place(
            $cart,
            $this->checkoutData($shipping, $payment),
            $customer
        );

        $this->expectException(LogicException::class);
        $result->order->items->first()->update(['discount_amount' => '1.0000']);
    }

    public function test_final_usage_limit_is_revalidated_before_usage_creation(): void
    {
        [$firstCart, , $firstCustomer, $shipping, $payment] = $this->scenario();
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'usage_limit' => 1,
        ]);
        $firstCart->update(['coupon_id' => $coupon->id]);
        $first = app(CheckoutOrderPlacementService::class)->place(
            $firstCart,
            $this->checkoutData($shipping, $payment),
            $firstCustomer
        );
        $this->assertTrue($first->successful);

        [$secondCart, , $secondCustomer] = $this->scenario();
        $secondCart->update(['coupon_id' => $coupon->id]);
        $second = app(CheckoutOrderPlacementService::class)->place(
            $secondCart,
            $this->checkoutData($shipping, $payment),
            $secondCustomer
        );

        $this->assertFalse($second->successful);
        $this->assertSame(['coupon_invalid'], $second->failureCodes());
        $this->assertDatabaseCount('coupon_usages', 1);
        $this->assertNull($secondCart->fresh()->coupon_id);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $secondCart->id]);
    }

    public function test_failure_after_usage_creation_rolls_back_order_usage_and_cart_changes(): void
    {
        [$cart, , $customer, $shipping, $payment] = $this->scenario();
        $coupon = Coupon::factory()->create(['is_active' => true]);
        $cart->update(['coupon_id' => $coupon->id]);
        $tokenService = app(GuestCartTokenService::class);
        $failingCartService = new class($tokenService, app(CouponEligibilityService::class)) extends CartService
        {
            public function clearForCheckout(Cart $cart, mixed $timestamp): void
            {
                throw new RuntimeException('Forced Cart clear failure.');
            }
        };
        $service = new CheckoutOrderPlacementService(
            app(CheckoutCartValidator::class),
            app(CheckoutService::class),
            app(OrderService::class),
            $failingCartService,
            $tokenService,
            app(CheckoutAddressResolver::class),
            app(CouponCartService::class),
            app(CouponUsageService::class),
            app(OrderSnapshotFactory::class),
        );

        try {
            $service->place($cart, $this->checkoutData($shipping, $payment), $customer);
            $this->fail('The forced failure did not abort placement.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced Cart clear failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('coupon_usages', 0);
        $this->assertSame($coupon->id, $cart->fresh()->coupon_id);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cart->id]);
    }

    private function scenario(): array
    {
        $tax = Tax::query()->firstOrCreate(
            ['name' => 'Standard'],
            ['rate' => 10, 'status' => true]
        );
        foreach ([
            ['currency', 'default_currency', 'USD', 'string'],
            ['tax', 'tax_mode', 'b2c', 'string'],
            ['tax', 'default_tax_id', (string) $tax->id, 'integer'],
            ['cart', 'lifetime_days', '30', 'integer'],
        ] as [$group, $key, $value, $type]) {
            Setting::updateOrCreate(compact('group', 'key'), compact('value', 'type'));
            cache()->forget("setting.{$group}.{$key}");
        }
        $customer = User::factory()->customer()->create();
        $cart = Cart::create([
            'user_id' => $customer->id,
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $product = Product::factory()->create([
            'type' => ProductType::Simple->value,
            'configurable_id' => null,
            'price' => '100.0000',
            'use_default_tax' => true,
            'status' => true,
            'is_visible_individually' => true,
        ]);
        $product->translations()->create([
            'locale' => 'en', 'name' => 'Coupon Product', 'url_key' => 'coupon-'.$product->id,
        ]);
        $product->inventory()->create([
            'quantity' => '10.0000', 'average_cost' => '20.0000', 'low_stock_alert' => '1.0000',
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'product_type' => CartItemType::Simple,
            'configuration_hash' => hash('sha256', 'simple-'.$product->id),
            'quantity' => '1.0000',
        ]);
        $shipping = ShippingMethod::factory()->create(['amount' => '5.0000', 'is_active' => true]);
        $payment = PaymentMethod::query()->where('code', 'cash_on_delivery')->first()
            ?? PaymentMethod::factory()->create(['code' => 'cash_on_delivery']);
        $payment->update(['is_active' => true]);

        return [$cart, $product, $customer, $shipping, $payment];
    }

    private function checkoutData(ShippingMethod $shipping, PaymentMethod $payment): array
    {
        $address = [
            'first_name' => 'Jane', 'last_name' => 'Customer', 'company' => null,
            'email' => 'jane@example.com', 'phone' => '70123456',
            'address_line_1' => 'Main Street', 'address_line_2' => null,
            'city' => 'Beirut', 'state' => 'Beirut', 'postal_code' => null,
            'country_code' => 'LB',
        ];

        return [
            'shipping_method' => $shipping->code,
            'payment_method' => $payment->code,
            'customer' => ['first_name' => 'Jane', 'last_name' => 'Customer', 'phone' => '70123456', 'email' => 'jane@example.com'],
            'address_source' => 'manual',
            'manual_address' => $address,
        ];
    }
}
