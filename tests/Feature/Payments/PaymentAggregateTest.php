<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Models\OrderPayment;
use App\Models\PaymentMethod;
use App\Services\OrderService;
use App\Services\PaymentStatusService;
use Database\Seeders\PaymentMethodSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PaymentAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_owns_one_obligation_with_immutable_snapshots_and_no_attempt(): void
    {
        $method = PaymentMethod::where('code', 'cash_on_delivery')->firstOrFail();
        $order = app(OrderService::class)->create($this->orderData($method->code));
        $payment = $order->payment()->firstOrFail();

        $this->assertMatchesRegularExpression('/^PAY-\d{4}-\d{6}$/', $payment->payment_number);
        $this->assertSame($method->id, $payment->payment_method_id);
        $this->assertSame($method->code, $payment->method_code);
        $this->assertSame($method->name, $payment->method_name);
        $this->assertSame(PaymentMethodType::Offline->value, $payment->method_type);
        $this->assertSame('10.0000', $payment->amount);
        $this->assertSame('USD', $payment->currency_code);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('0.0000', $payment->paid_amount);
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_mark_paid_creates_a_real_attempt_and_updates_projection_atomically(): void
    {
        $method = PaymentMethod::where('code', 'cash_on_delivery')->firstOrFail();
        $order = app(OrderService::class)->create($this->orderData($method->code));

        $result = app(PaymentStatusService::class)->markPaid($order);
        $payment = $result->payment()->firstOrFail();
        $attempt = $payment->attempts()->firstOrFail();

        $this->assertSame(PaymentStatus::Paid->value, $result->payment_status);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame($payment->amount, $payment->paid_amount);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(PaymentAttemptStatus::Paid, $attempt->status);
        $this->assertNotNull($attempt->completed_at);
    }

    public function test_obligation_snapshots_and_terminal_attempts_are_immutable(): void
    {
        $method = PaymentMethod::where('code', 'cash_on_delivery')->firstOrFail();
        $order = app(OrderService::class)->create($this->orderData($method->code));
        app(PaymentStatusService::class)->markFailed($order);
        $payment = $order->payment()->firstOrFail();
        $attempt = $payment->attempts()->firstOrFail();

        try {
            $payment->update(['method_name' => 'Changed']);
            $this->fail('An immutable obligation snapshot was changed.');
        } catch (LogicException $exception) {
            $this->assertSame('The payment obligation method_name snapshot is immutable.', $exception->getMessage());
        }

        try {
            $attempt->update(['failure_message' => 'Changed']);
            $this->fail('A terminal attempt was changed.');
        } catch (LogicException $exception) {
            $this->assertSame('Terminal payment attempts are immutable.', $exception->getMessage());
        }
    }

    public function test_payment_numbers_and_order_ownership_are_unique(): void
    {
        $method = PaymentMethod::where('code', 'cash_on_delivery')->firstOrFail();
        $order = app(OrderService::class)->create($this->orderData($method->code));
        $payment = $order->payment()->firstOrFail();

        $this->expectException(UniqueConstraintViolationException::class);

        OrderPayment::query()->insert(array_merge(
            $payment->getAttributes(),
            ['id' => null]
        ));
    }

    public function test_attempt_status_is_separate_from_aggregate_status(): void
    {
        $this->assertSame('awaiting_verification', PaymentStatus::AwaitingVerification->value);
        $this->assertSame('requires_action', PaymentAttemptStatus::RequiresAction->value);
        $this->assertFalse(PaymentAttemptStatus::Processing->isTerminal());
        $this->assertTrue(PaymentAttemptStatus::Expired->isTerminal());
    }

    public function test_payment_method_seeding_preserves_administrator_edits(): void
    {
        $method = PaymentMethod::where('code', 'manual_wallet_transfer')->firstOrFail();
        $method->update([
            'name' => 'Administrator Wallet Name',
            'is_active' => false,
            'requires_payment_before_processing' => false,
            'sort_order' => 41,
        ]);

        $this->seed(PaymentMethodSeeder::class);

        $method->refresh();
        $this->assertSame('Administrator Wallet Name', $method->name);
        $this->assertFalse($method->is_active);
        $this->assertFalse($method->requires_payment_before_processing);
        $this->assertSame(41, $method->sort_order);
        $this->assertFalse(
            PaymentMethod::where('code', 'online_card')->where('is_active', true)->exists()
        );
    }

    private function orderData(string $paymentMethod): array
    {
        return [
            'user_id' => null,
            'customer_email' => 'payment@example.com',
            'customer_first_name' => 'Payment',
            'customer_last_name' => 'Customer',
            'customer_phone' => null,
            'locale' => 'en',
            'currency_code' => 'USD',
            'payment_method' => $paymentMethod,
            'subtotal' => '10.0000',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '10.0000',
            'customer_notes' => null,
            'admin_notes' => null,
            'placed_at' => now(),
            'items' => [[
                'product_id' => null,
                'product_type' => 'simple',
                'sku' => 'PAYMENT-SKU',
                'name' => 'Payment Product',
                'quantity' => 1,
                'original_unit_price' => '10.0000',
                'unit_price' => '10.0000',
                'tax_amount' => '0.0000',
                'row_subtotal' => '10.0000',
                'row_total' => '10.0000',
                'is_inventory_item' => false,
            ]],
            'billing_address' => $this->address(),
            'shipping_address' => $this->address(),
            'payment' => [],
        ];
    }

    private function address(): array
    {
        return [
            'first_name' => 'Payment',
            'last_name' => 'Customer',
            'company' => null,
            'email' => 'payment@example.com',
            'phone' => null,
            'address_line_1' => 'Payment Street',
            'address_line_2' => null,
            'city' => 'Beirut',
            'state' => null,
            'postal_code' => null,
            'country_code' => 'LB',
        ];
    }
}
