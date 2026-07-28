<?php

namespace Tests\Feature\Payments;

use App\Models\DocumentSequence;
use App\Models\PaymentMethod;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class PaymentArchitectureMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_legacy_rows_are_converted_without_losing_history(): void
    {
        $this->restoreLegacyTable();
        $this->seedLegacyMatrix();

        $migration = require database_path('migrations/2026_07_28_000006_align_payment_obligations_and_attempts.php');
        $migration->up();

        $this->assertDatabaseCount('order_payments', 11);
        $this->assertDatabaseCount('payment_attempts', 16);
        $this->assertSame(11, DB::table('order_payments')->distinct()->count('order_id'));
        $this->assertSame(16, DB::table('payment_attempts')->distinct()->count('id'));
        $this->assertSame(11, DocumentSequence::where('document_type', 'payment')->value('last_number'));
        $this->assertMatchesRegularExpression(
            '/^PAY-\d{4}-000001$/',
            DB::table('order_payments')->orderBy('order_id')->value('payment_number')
        );

        $onlinePayment = DB::table('order_payments')->where('order_id', 1)->first();
        $this->assertSame('online_card', $onlinePayment->method_code);
        $this->assertSame('Online Card', $onlinePayment->method_name);
        $this->assertSame('gateway', $onlinePayment->method_type);
        $this->assertSame('33.4900', number_format((float) $onlinePayment->amount, 4, '.', ''));
        $this->assertSame('33.4900', number_format((float) $onlinePayment->paid_amount, 4, '.', ''));
        $this->assertNotNull($onlinePayment->paid_at);
        $this->assertFalse(PaymentMethod::where('code', 'online_card')->firstOrFail()->is_active);

        $attempts = DB::table('payment_attempts')
            ->where('order_payment_id', DB::table('order_payments')->where('order_id', 2)->value('id'))
            ->orderBy('attempt_number')
            ->get();
        $this->assertSame([1, 2, 3], $attempts->pluck('attempt_number')->all());
        $this->assertSame(['failed', 'failed', 'pending'], $attempts->pluck('status')->all());
        $this->assertSame('REFERENCE-2-1', $attempts->first()->transaction_reference);
        $this->assertSame('Declined 2-1', $attempts->first()->failure_message);
        $this->assertNotNull($attempts->first()->completed_at);
        $this->assertSame(
            2,
            json_decode($attempts->first()->metadata_json, true, flags: JSON_THROW_ON_ERROR)['legacy_payment_id']
        );
        $this->assertFalse(Schema::hasTable('legacy_order_payments'));
    }

    public function test_unknown_method_fails_before_any_legacy_change(): void
    {
        $this->restoreLegacyTable();
        PaymentMethod::create([
            'code' => 'unknown_method',
            'name' => 'Unknown Method',
            'type' => 'offline',
            'is_active' => false,
            'requires_payment_before_processing' => false,
            'sort_order' => 99,
        ]);
        $orderId = $this->insertOrder(1, 'pending', 'unknown_method');
        $this->insertLegacyPayment($orderId, 1, 'pending', 'unknown_method');

        $migration = require database_path('migrations/2026_07_28_000006_align_payment_obligations_and_attempts.php');

        try {
            $migration->up();
            $this->fail('An unknown method was converted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('unknown payment method', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('order_payments'));
        $this->assertFalse(Schema::hasTable('order_payments_v2'));
        $this->assertDatabaseCount('order_payments', 1);
        $this->assertDatabaseMissing('document_sequences', ['document_type' => 'payment', 'last_number' => 1]);
    }

    public function test_ambiguous_paid_history_fails_before_conversion(): void
    {
        $this->restoreLegacyTable();
        $orderId = $this->insertOrder(1, 'paid', 'cash_on_delivery');
        $this->insertLegacyPayment($orderId, 1, 'paid', 'cash_on_delivery');
        $this->insertLegacyPayment($orderId, 2, 'paid', 'cash_on_delivery');

        $migration = require database_path('migrations/2026_07_28_000006_align_payment_obligations_and_attempts.php');

        try {
            $migration->up();
            $this->fail('Ambiguous paid history was converted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'does not have exactly one provable paid legacy Payment',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('order_payments', 2);
        $this->assertFalse(Schema::hasTable('payment_attempts_v2'));
    }

    private function restoreLegacyTable(): void
    {
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('order_payments');
        DB::table('orders')->delete();
        DocumentSequence::where('document_type', 'payment')->update(['last_number' => 0]);

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('method');
            $table->string('status')->default('pending');
            $table->decimal('amount', 15, 4);
            $table->string('transaction_reference')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    private function seedLegacyMatrix(): void
    {
        PaymentMethod::query()->updateOrCreate(
            ['code' => 'online_card'],
            [
                'name' => 'Online Card',
                'type' => 'gateway',
                'is_active' => false,
                'requires_payment_before_processing' => true,
                'sort_order' => 99,
            ]
        );

        $plans = [
            1 => ['paid', 'online_card', ['paid']],
            2 => ['pending', 'online_card', ['failed', 'failed', 'pending']],
            3 => ['paid', 'online_card', ['failed', 'paid']],
            4 => ['pending', 'cash_on_delivery', ['pending']],
            5 => ['paid', 'online_card', ['paid']],
            6 => ['paid', 'online_card', ['paid']],
            7 => ['paid', 'cash_on_delivery', ['paid']],
            8 => ['paid', 'online_card', ['failed', 'failed', 'paid']],
            9 => ['cancelled', 'cash_on_delivery', ['cancelled']],
            10 => ['cancelled', 'cash_on_delivery', ['cancelled']],
            11 => ['cancelled', 'cash_on_delivery', ['cancelled']],
        ];

        $legacyId = 1;

        foreach ($plans as $orderNumber => [$aggregateStatus, $method, $statuses]) {
            $orderId = $this->insertOrder($orderNumber, $aggregateStatus, $method);

            foreach ($statuses as $attempt => $status) {
                $this->insertLegacyPayment($orderId, $legacyId++, $status, $method, $attempt + 1);
            }
        }
    }

    private function insertOrder(int $number, string $status, string $method): int
    {
        $timestamp = Carbon::parse('2026-01-01 10:00:00')->addDays($number);

        return DB::table('orders')->insertGetId([
            'order_number' => 'ORD-2026-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'customer_email' => "legacy{$number}@example.com",
            'customer_first_name' => 'Legacy',
            'customer_last_name' => "Order {$number}",
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => $status,
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => $method,
            'requires_payment_before_processing' => $method !== 'cash_on_delivery',
            'subtotal' => '33.4900',
            'discount_total' => '0.0000',
            'shipping_total' => '0.0000',
            'tax_total' => '0.0000',
            'grand_total' => '33.4900',
            'placed_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function insertLegacyPayment(
        int $orderId,
        int $legacyId,
        string $status,
        string $method,
        int $offset = 1
    ): void {
        $createdAt = Carbon::parse('2026-02-01 10:00:00')->addHours($legacyId + $offset);
        $completedAt = $createdAt->copy()->addHour();

        DB::table('order_payments')->insert([
            'id' => $legacyId,
            'order_id' => $orderId,
            'method' => $method,
            'status' => $status,
            'amount' => '33.4900',
            'transaction_reference' => "REFERENCE-{$orderId}-{$offset}",
            'failure_message' => $status === 'failed' ? "Declined {$orderId}-{$offset}" : null,
            'paid_at' => $status === 'paid' ? $completedAt : null,
            'failed_at' => $status === 'failed' ? $completedAt : null,
            'created_at' => $createdAt,
            'updated_at' => $status === 'pending' ? $createdAt : $completedAt,
        ]);
    }
}
