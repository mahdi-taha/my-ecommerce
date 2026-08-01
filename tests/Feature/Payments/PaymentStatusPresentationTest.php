<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class PaymentStatusPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_aggregate_and_attempt_status_has_english_and_arabic_labels(): void
    {
        foreach (['en', 'ar'] as $locale) {
            foreach (PaymentStatus::cases() as $status) {
                $key = 'shop.checkout.status.payment.'.$status->value;

                $this->assertTrue(Lang::has($key, $locale));
                $this->assertNotSame($key, Lang::get($key, [], $locale));
            }

            foreach (PaymentAttemptStatus::cases() as $status) {
                $key = 'shop.checkout.status.payment_attempt.'.$status->value;

                $this->assertTrue(Lang::has($key, $locale));
                $this->assertNotSame($key, Lang::get($key, [], $locale));
            }
        }
    }

    public function test_admin_order_index_exposes_and_styles_every_aggregate_status(): void
    {
        $admin = User::factory()->create();

        $page = $this->actingAs($admin, 'admin')->get(route('admin.orders.index'));

        foreach (PaymentStatus::cases() as $status) {
            $page->assertSee('value="'.$status->value.'"', false);

            $order = $this->order(null, $status);
            $response = $this->actingAs($admin, 'admin')
                ->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->getJson(route('admin.orders.index', array_merge(
                    $this->dataTableParameters(),
                    ['payment_status' => $status->value]
                )));

            $response->assertOk()
                ->assertJsonFragment(['order_number' => $order->order_number]);
            $this->assertStringContainsString(
                $this->aggregateBadgeClasses()[$status->value],
                $response->json('data.0.payment_status')
            );
        }
    }

    public function test_admin_customer_and_customer_order_pages_render_every_aggregate_status(): void
    {
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->create();

        foreach (PaymentStatus::cases() as $status) {
            $this->order($customer, $status);
        }

        $adminResponse = $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer));

        foreach (PaymentStatus::cases() as $status) {
            $label = ucwords(str_replace('_', ' ', $status->value));
            $adminResponse->assertSee(
                '<span class="badge '.$this->aggregateBadgeClasses()[$status->value].'">'.$label.'</span>',
                false
            );
        }

        foreach (['en', 'ar'] as $locale) {
            $history = $this->withSession(['storefront_locale' => $locale])
                ->actingAs($customer, 'customer')
                ->get(route('shop.account.orders.index'));

            foreach (PaymentStatus::cases() as $status) {
                $history->assertSee(
                    Lang::get('shop.checkout.status.payment.'.$status->value, [], $locale)
                );
            }

            $order = $customer->orders()->where('payment_status', PaymentStatus::AwaitingVerification->value)->firstOrFail();
            $this->addCheckoutSnapshots($order);
            $label = Lang::get('shop.checkout.status.payment.awaiting_verification', [], $locale);

            $this->actingAs($customer, 'customer')
                ->get(route('shop.account.orders.show', $order))
                ->assertOk()
                ->assertSee($label);

            $this->actingAs($customer, 'customer')
                ->get(route('shop.checkout.success', $order))
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_admin_order_details_keep_aggregate_and_attempt_presentations_independent(): void
    {
        $admin = User::factory()->create();
        $order = $this->order(null, PaymentStatus::PartiallyRefunded);
        $payment = OrderPayment::query()->create([
            'payment_number' => 'PAY-PRESENTATION-000001',
            'order_id' => $order->id,
            'payment_method_id' => null,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => PaymentStatus::PartiallyRefunded,
            'paid_amount' => '10.0000',
            'paid_at' => now(),
        ]);

        foreach (PaymentAttemptStatus::cases() as $index => $status) {
            PaymentAttempt::query()->create([
                'order_payment_id' => $payment->id,
                'attempt_number' => $index + 1,
                'provider' => null,
                'status' => $status,
                'amount' => '10.0000',
                'currency_code' => 'USD',
                'initiated_at' => now(),
                'completed_at' => $status->isTerminal() ? now() : null,
            ]);
        }

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order));

        $response->assertOk()->assertSee(
            '<span class="badge bg-dark">Partially Refunded</span>',
            false
        );

        foreach (PaymentAttemptStatus::cases() as $status) {
            $label = Lang::get('shop.checkout.status.payment_attempt.'.$status->value, [], 'en');
            $response->assertSee(
                '<span class="badge '.$this->attemptBadgeClasses()[$status->value].'">'.$label.'</span>',
                false
            );
        }
    }

    private function order(?User $customer, PaymentStatus $paymentStatus): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-PRESENTATION-'.fake()->unique()->numerify('######'),
            'user_id' => $customer?->id,
            'customer_email' => $customer?->email ?? 'presentation@example.test',
            'customer_first_name' => 'Payment',
            'customer_last_name' => 'Presentation',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => $paymentStatus->value,
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

    private function addCheckoutSnapshots(Order $order): void
    {
        if ($order->addresses()->exists()) {
            return;
        }

        foreach (['billing', 'shipping'] as $type) {
            $order->addresses()->create([
                'type' => $type,
                'first_name' => 'Payment',
                'last_name' => 'Presentation',
                'company' => null,
                'email' => 'presentation@example.test',
                'phone' => '70123456',
                'address_line_1' => 'Presentation Street',
                'address_line_2' => null,
                'city' => 'Beirut',
                'state' => null,
                'postal_code' => null,
                'country_code' => 'LB',
            ]);
        }

        $order->shipping()->create([
            'shipping_method_id' => null,
            'shipping_method_code' => 'store_pickup',
            'shipping_method_name' => 'Store Pickup',
            'shipping_method_type' => 'pickup',
            'shipping_amount' => '0.0000',
        ]);

        OrderPayment::query()->create([
            'payment_number' => 'PAY-PRESENTATION-'.$order->id,
            'order_id' => $order->id,
            'payment_method_id' => null,
            'method_code' => 'cash_on_delivery',
            'method_name' => 'Cash on Delivery',
            'method_type' => 'offline',
            'amount' => '10.0000',
            'currency_code' => 'USD',
            'status' => PaymentStatus::AwaitingVerification,
            'paid_amount' => '0.0000',
            'paid_at' => null,
        ]);
    }

    /** @return array<string, string> */
    private function aggregateBadgeClasses(): array
    {
        return [
            'pending' => 'bg-warning text-dark',
            'awaiting_verification' => 'bg-info text-dark',
            'paid' => 'bg-success',
            'partially_paid' => 'bg-info text-dark',
            'failed' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'refunded' => 'bg-dark',
            'partially_refunded' => 'bg-dark',
        ];
    }

    /** @return array<string, string> */
    private function attemptBadgeClasses(): array
    {
        return [
            'pending' => 'bg-warning text-dark',
            'requires_action' => 'bg-info text-dark',
            'processing' => 'bg-primary',
            'paid' => 'bg-success',
            'failed' => 'bg-danger',
            'cancelled' => 'bg-secondary',
            'expired' => 'bg-dark',
        ];
    }

    /** @return array<string, mixed> */
    private function dataTableParameters(): array
    {
        $columns = collect([
            'order_number',
            'customer',
            'placed_at',
            'items_count',
            'grand_total',
            'status',
            'payment_status',
            'fulfillment_status',
            'action',
        ])->map(fn (string $column): array => [
            'data' => $column,
            'name' => $column,
            'searchable' => false,
            'orderable' => false,
            'search' => ['value' => '', 'regex' => false],
        ])->all();

        return [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => $columns,
            'search' => ['value' => '', 'regex' => false],
        ];
    }
}
