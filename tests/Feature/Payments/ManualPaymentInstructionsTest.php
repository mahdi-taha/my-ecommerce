<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentMethodType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Presenters\ManualPaymentInstructionsPresenter;
use App\Services\GuestCartTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPaymentInstructionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_wallet_order_shows_configured_instructions_and_authoritative_whatsapp_message(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->manualOrder($customer, 'manual_wallet_transfer', 'Manual Wallet Snapshot');
        $this->settings([
            'manual_whatsapp_number' => '+961 (70) 123-456',
            'manual_wallet_title' => 'Wallet Payment',
            'manual_wallet_name' => 'Whish',
            'manual_wallet_number' => '70123456',
            'manual_wallet_instructions' => '',
        ]);

        $before = $this->state($order);
        $response = $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', [
            'order' => $order,
            'amount' => '0.01',
            'order_number' => 'FORGED',
        ]));

        $presentation = app(ManualPaymentInstructionsPresenter::class)->present($order->fresh('payment'));
        $decodedMessage = urldecode(parse_url($presentation['whatsapp_url'], PHP_URL_QUERY));

        $response->assertOk()
            ->assertSee('Wallet Payment')
            ->assertSee('Whish')
            ->assertSee('70123456')
            ->assertDontSee(__('shop.payment_instructions.instructions'))
            ->assertSee('https://wa.me/96170123456?text=', false);
        $this->assertStringContainsString($order->order_number, $decodedMessage);
        $this->assertStringContainsString('Manual Wallet Snapshot', $decodedMessage);
        $this->assertStringContainsString('$ 45.00', $decodedMessage);
        $this->assertStringNotContainsString('FORGED', $decodedMessage);
        $this->assertStringNotContainsString('0.01', $decodedMessage);
        $this->assertSame($before, $this->state($order));
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_bank_order_shows_only_nonempty_bank_fields(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->manualOrder($customer, 'manual_bank_transfer', 'Manual Bank Snapshot');
        $this->settings([
            'manual_bank_name' => 'Example Bank',
            'manual_bank_account_name' => 'Example Store',
            'manual_bank_account_number' => '',
            'manual_bank_iban' => 'LB00TEST',
            'manual_bank_swift' => '',
        ]);

        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Example Bank')
            ->assertSee('Example Store')
            ->assertSee('LB00TEST')
            ->assertDontSee(__('shop.payment_instructions.account_number'))
            ->assertDontSee(__('shop.payment_instructions.swift'));
    }

    public function test_only_pending_and_paid_manual_payment_states_render(): void
    {
        $customer = User::factory()->customer()->create();

        foreach (['failed', 'cancelled'] as $status) {
            $order = $this->manualOrder($customer, 'manual_wallet_transfer', 'Manual Wallet', $status);
            $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $order))
                ->assertOk()->assertDontSee(__('shop.payment_instructions.heading'));
        }

        $paid = $this->manualOrder($customer, 'manual_wallet_transfer', 'Manual Wallet', 'paid');
        $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $paid))
            ->assertOk()
            ->assertSee(__('shop.payment_instructions.payment_received'))
            ->assertDontSee(__('shop.payment_instructions.send_proof'));
    }

    public function test_cod_and_cancelled_manual_orders_do_not_render_manual_instructions(): void
    {
        $customer = User::factory()->customer()->create();
        $cod = $this->manualOrder($customer, 'cash_on_delivery', 'Cash on Delivery');
        $cancelled = $this->manualOrder($customer, 'manual_wallet_transfer', 'Manual Wallet');
        $cancelled->update(['status' => 'cancelled']);

        foreach ([$cod, $cancelled] as $order) {
            $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $order))
                ->assertOk()->assertDontSee(__('shop.payment_instructions.heading'));
        }
    }

    public function test_missing_or_invalid_whatsapp_number_hides_link_safely(): void
    {
        $customer = User::factory()->customer()->create();
        $order = $this->manualOrder($customer, 'manual_wallet_transfer', 'Manual Wallet');

        foreach (['', '000-12'] as $number) {
            $this->settings(['manual_whatsapp_number' => $number]);
            $this->actingAs($customer, 'customer')->get(route('shop.account.orders.show', $order))
                ->assertOk()
                ->assertSee(__('shop.payment_instructions.whatsapp_unavailable'))
                ->assertDontSee('https://wa.me/', false);
        }
    }

    public function test_another_customer_cannot_access_manual_payment_instructions(): void
    {
        $order = $this->manualOrder(User::factory()->customer()->create(), 'manual_wallet_transfer', 'Manual Wallet');

        $this->actingAs(User::factory()->customer()->create(), 'customer')
            ->get(route('shop.account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_authorized_guest_checkout_success_reuses_manual_payment_presentation(): void
    {
        $token = str_repeat('a', 64);
        $cart = Cart::create([
            'user_id' => null,
            'guest_token_hash' => hash('sha256', $token),
            'last_activity_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $order = $this->manualOrder(null, 'manual_bank_transfer', 'Manual Bank Snapshot');
        $this->settings(['manual_bank_name' => 'Guest Bank']);

        $this->withSession(['shop.checkout.guest_orders' => [(string) $order->id => $cart->id]])
            ->withCookie(GuestCartTokenService::COOKIE_NAME, $token)
            ->get(route('shop.checkout.success', $order))
            ->assertOk()
            ->assertSee(__('shop.payment_instructions.heading'))
            ->assertSee('Guest Bank');
    }

    public function test_whatsapp_message_is_localized_in_english_and_arabic(): void
    {
        $order = $this->manualOrder(User::factory()->customer()->create(), 'manual_wallet_transfer', 'Snapshot Method');
        $this->settings(['manual_whatsapp_number' => '96170123456']);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);
            $presentation = app(ManualPaymentInstructionsPresenter::class)->present($order->fresh('payment'));
            $message = urldecode(parse_url($presentation['whatsapp_url'], PHP_URL_QUERY));

            $this->assertStringContainsString(
                trans('shop.payment_instructions.whatsapp_message', [
                    'order_number' => $order->order_number,
                    'customer_name' => 'Snapshot Customer',
                    'payment_method' => 'Snapshot Method',
                    'amount' => '$ 45.00',
                    'currency' => 'USD',
                ], $locale),
                $message
            );
        }
    }

    private function manualOrder(?User $customer, string $methodCode, string $methodName, string $status = 'pending'): Order
    {
        $method = PaymentMethod::create([
            'code' => $methodCode.'-'.fake()->unique()->numerify('####'),
            'name' => $methodName,
            'type' => $methodCode === 'cash_on_delivery' ? PaymentMethodType::Offline : PaymentMethodType::ManualTransfer,
            'is_active' => true,
            'requires_payment_before_processing' => $methodCode !== 'cash_on_delivery',
            'sort_order' => 1,
        ]);
        $order = Order::create([
            'order_number' => 'ORD-2026-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'guest@example.com',
            'customer_first_name' => 'Snapshot',
            'customer_last_name' => 'Customer',
            'customer_phone' => '70123456',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => $status,
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => $methodCode,
            'requires_payment_before_processing' => $methodCode !== 'cash_on_delivery',
            'subtotal' => '45.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '45.0000',
            'placed_at' => now(),
        ]);
        $order->payment()->create([
            'payment_number' => 'PAY-2026-'.fake()->unique()->numerify('######'),
            'payment_method_id' => $method->id,
            'method_code' => $methodCode,
            'method_name' => $methodName,
            'method_type' => $method->type->value,
            'amount' => '45.0000',
            'currency_code' => 'USD',
            'status' => $status,
            'paid_amount' => $status === 'paid' ? '45.0000' : '0.0000',
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
        foreach (['billing', 'shipping'] as $type) {
            $order->addresses()->create([
                'type' => $type,
                'first_name' => 'Snapshot',
                'last_name' => 'Customer',
                'company' => null,
                'email' => 'snapshot@example.com',
                'phone' => '70123456',
                'address_line_1' => 'Snapshot Street',
                'address_line_2' => null,
                'city' => 'Beirut',
                'state' => null,
                'postal_code' => null,
                'country_code' => 'LB',
            ]);
        }
        $order->shipping()->create([
            'shipping_method_id' => null,
            'shipping_method_code' => 'delivery',
            'shipping_method_name' => 'Delivery',
            'shipping_method_type' => 'delivery',
            'shipping_amount' => '0.0000',
        ]);

        return $order;
    }

    private function settings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(
                ['group' => 'payments', 'key' => $key],
                ['value' => $value, 'type' => str_ends_with($key, 'instructions') ? 'textarea' : 'text']
            );
            cache()->forget('setting.payments.'.$key);
        }
    }

    private function state(Order $order): array
    {
        return [
            'order' => $order->fresh()->toArray(),
            'payment' => $order->payment()->first()->toArray(),
            'attempts' => $order->payment()->first()->attempts()->count(),
        ];
    }
}
