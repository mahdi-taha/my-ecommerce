<?php

namespace Tests\Feature\CustomerAccount;

use App\Enums\AccountType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_print_requires_authentication_and_exact_ownership(): void
    {
        $owner = User::factory()->customer()->create();
        $order = $this->order($owner);
        $route = route('shop.account.orders.print', ['locale' => 'en', 'order' => $order]);

        $this->get($route)->assertRedirect(route('customer.login'));
        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get($route)
            ->assertNotFound();
        $this->actingAs($owner, 'customer')
            ->get($route)
            ->assertOk()
            ->assertSee($order->order_number);
    }

    public function test_manual_customer_cannot_access_customer_order_printing(): void
    {
        $manualCustomer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'has_account' => false,
            'is_active' => true,
        ]);
        $order = $this->order($manualCustomer);

        $this->actingAs($manualCustomer, 'customer')
            ->get(route('shop.account.orders.print', ['locale' => 'en', 'order' => $order]))
            ->assertRedirect(route('customer.login'));
    }

    public function test_customer_print_is_localized_by_the_route_and_supports_rtl(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->order($customer);

        $this->actingAs($customer, 'customer')
            ->get(route('shop.account.orders.print', ['locale' => 'ar', 'order' => $order]))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('ملخص الطلب')
            ->assertDontSee('Order Summary');
    }

    private function order(User $customer): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-CUSTOMER-'.fake()->unique()->numerify('######'),
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Customer',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'requires_payment_before_processing' => false,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'placed_at' => now(),
        ]);
    }
}
