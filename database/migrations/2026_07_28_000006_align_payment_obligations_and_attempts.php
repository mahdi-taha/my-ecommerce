<?php

use App\Services\PaymentNumberGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const METHOD_TYPES = [
        'cash_on_delivery' => 'offline',
        'manual_wallet_transfer' => 'manual_transfer',
        'manual_bank_transfer' => 'manual_transfer',
        'online_card' => 'gateway',
    ];

    private const AGGREGATE_STATUSES = [
        'pending',
        'awaiting_verification',
        'paid',
        'partially_paid',
        'failed',
        'cancelled',
        'refunded',
        'partially_refunded',
    ];

    private const LEGACY_ATTEMPT_STATUSES = [
        'pending',
        'paid',
        'failed',
        'cancelled',
    ];

    public function up(): void
    {
        $orders = DB::table('orders')->orderBy('id')->get();
        $legacyPayments = DB::table('order_payments')
            ->orderBy('order_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('order_id');
        $methods = DB::table('payment_methods')->get()->keyBy('code');

        $this->validateLegacyData($orders, $legacyPayments, $methods);

        if (Schema::hasTable('order_payments_v2') || Schema::hasTable('payment_attempts_v2')) {
            throw new RuntimeException(
                'Payment alignment staging tables already exist. Resolve the prior failed migration before retrying.'
            );
        }

        $this->createStagingTables();

        try {
            DB::transaction(function () use ($orders, $legacyPayments, $methods): void {
                DB::table('document_sequences')->insertOrIgnore([
                    'document_type' => 'payment',
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($orders as $order) {
                    $rows = $legacyPayments->get($order->id, collect());
                    $method = $methods->get($order->payment_method);
                    $paidRow = $rows->firstWhere('status', 'paid');
                    $paymentId = DB::table('order_payments_v2')->insertGetId([
                        'payment_number' => app(PaymentNumberGenerator::class)->generate(),
                        'order_id' => $order->id,
                        'payment_method_id' => $method->id,
                        'method_code' => $order->payment_method,
                        'method_name' => $method->name,
                        'method_type' => self::METHOD_TYPES[$order->payment_method],
                        'amount' => $order->grand_total,
                        'currency_code' => $order->currency_code,
                        'status' => $order->payment_status,
                        'paid_amount' => $order->payment_status === 'paid' ? $order->grand_total : '0.0000',
                        'paid_at' => $order->payment_status === 'paid' ? $paidRow?->paid_at : null,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);

                    foreach ($rows->values() as $index => $legacyPayment) {
                        DB::table('payment_attempts_v2')->insert([
                            'order_payment_id' => $paymentId,
                            'attempt_number' => $index + 1,
                            'provider' => null,
                            'status' => $legacyPayment->status,
                            'amount' => $legacyPayment->amount,
                            'currency_code' => $order->currency_code,
                            'transaction_reference' => $legacyPayment->transaction_reference,
                            'customer_note' => null,
                            'provider_transaction_id' => null,
                            'failure_code' => null,
                            'failure_message' => $legacyPayment->failure_message,
                            'metadata_json' => json_encode([
                                'legacy_payment_id' => $legacyPayment->id,
                            ], JSON_THROW_ON_ERROR),
                            'initiated_at' => $legacyPayment->created_at,
                            'completed_at' => $this->completedAt($legacyPayment),
                            'created_at' => $legacyPayment->created_at,
                            'updated_at' => $legacyPayment->updated_at,
                        ]);
                    }
                }

                $this->validateConvertedData($orders->count(), $legacyPayments->flatten(1)->count());
            });
        } catch (Throwable $exception) {
            Schema::dropIfExists('payment_attempts_v2');
            Schema::dropIfExists('order_payments_v2');

            throw $exception;
        }

        Schema::rename('order_payments', 'legacy_order_payments');
        Schema::rename('order_payments_v2', 'order_payments');
        Schema::rename('payment_attempts_v2', 'payment_attempts');

        $this->validateConvertedData($orders->count(), $legacyPayments->flatten(1)->count(), false);

        Schema::drop('legacy_order_payments');
    }

    public function down(): void
    {
        throw new RuntimeException('The Payment obligation and attempt conversion is forward-only.');
    }

    private function validateLegacyData($orders, $legacyPayments, $methods): void
    {
        $errors = [];

        foreach ($orders as $order) {
            $rows = $legacyPayments->get($order->id, collect());

            if (! in_array($order->payment_status, self::AGGREGATE_STATUSES, true)) {
                $errors[] = "Order {$order->id} has unknown aggregate payment status [{$order->payment_status}]";
            }

            if (! isset(self::METHOD_TYPES[$order->payment_method]) || ! $methods->has($order->payment_method)) {
                $errors[] = "Order {$order->id} has unknown payment method [{$order->payment_method}]";
            }

            if ($order->grand_total === null || $order->currency_code === null) {
                $errors[] = "Order {$order->id} has no authoritative payment amount or currency";
            }

            foreach ($rows as $row) {
                if (! in_array($row->status, self::LEGACY_ATTEMPT_STATUSES, true)) {
                    $errors[] = "Legacy Payment {$row->id} for Order {$order->id} has unknown status [{$row->status}]";
                }

                if (! isset(self::METHOD_TYPES[$row->method]) || ! $methods->has($row->method)) {
                    $errors[] = "Legacy Payment {$row->id} for Order {$order->id} has unknown method [{$row->method}]";
                } elseif ($row->method !== $order->payment_method) {
                    $errors[] = "Legacy Payment {$row->id} method [{$row->method}] conflicts with Order {$order->id} snapshot [{$order->payment_method}]";
                }

                if ($row->amount === null) {
                    $errors[] = "Legacy Payment {$row->id} for Order {$order->id} has no amount";
                }

                if ($row->status === 'paid' && $row->paid_at === null) {
                    $errors[] = "Legacy paid Payment {$row->id} for Order {$order->id} has no paid timestamp";
                }

                if ($row->status === 'failed' && $row->failed_at === null) {
                    $errors[] = "Legacy failed Payment {$row->id} for Order {$order->id} has no failed timestamp";
                }
            }

            if ($order->payment_status === 'paid' && $rows->where('status', 'paid')->count() !== 1) {
                $errors[] = "Paid Order {$order->id} does not have exactly one provable paid legacy Payment";
            }
        }

        $orphanIds = $legacyPayments
            ->keys()
            ->diff($orders->pluck('id'))
            ->values()
            ->all();

        if ($orphanIds !== []) {
            $errors[] = 'Legacy payments reference missing Order IDs: '.implode(', ', $orphanIds);
        }

        if ($errors !== []) {
            throw new RuntimeException(
                "Payment alignment preflight failed:\n- ".implode("\n- ", $errors)
            );
        }
    }

    private function createStagingTables(): void
    {
        Schema::create('order_payments_v2', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('method_code');
            $table->string('method_name');
            $table->string('method_type');
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3);
            $table->string('status');
            $table->decimal('paid_amount', 15, 4)->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_attempts_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')
                ->constrained('order_payments_v2')
                ->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('provider')->nullable();
            $table->string('status');
            $table->decimal('amount', 15, 4);
            $table->string('currency_code', 3);
            $table->string('transaction_reference')->nullable();
            $table->text('customer_note')->nullable();
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['order_payment_id', 'attempt_number']);
            $table->index(['order_payment_id', 'status']);
            $table->index(['provider', 'status']);
            $table->index('transaction_reference');
        });
    }

    private function validateConvertedData(int $orderCount, int $legacyCount, bool $staged = true): void
    {
        $obligationTable = $staged ? 'order_payments_v2' : 'order_payments';
        $attemptTable = $staged ? 'payment_attempts_v2' : 'payment_attempts';

        if (DB::table($obligationTable)->count() !== $orderCount) {
            throw new RuntimeException('Payment alignment did not create exactly one obligation per Order.');
        }

        if (DB::table($attemptTable)->count() !== $legacyCount) {
            throw new RuntimeException('Payment alignment did not preserve every legacy payment row as an attempt.');
        }

        $mismatched = DB::table($obligationTable)
            ->join('orders', "{$obligationTable}.order_id", '=', 'orders.id')
            ->whereColumn("{$obligationTable}.status", '!=', 'orders.payment_status')
            ->exists();

        if ($mismatched) {
            throw new RuntimeException('Payment obligation statuses do not match the Order projections.');
        }

        $duplicateAttempts = DB::table($attemptTable)
            ->select(['order_payment_id', 'attempt_number'])
            ->groupBy(['order_payment_id', 'attempt_number'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateAttempts) {
            throw new RuntimeException('Payment alignment created duplicate attempt numbers.');
        }
    }

    private function completedAt(object $legacyPayment): mixed
    {
        return match ($legacyPayment->status) {
            'paid' => $legacyPayment->paid_at,
            'failed' => $legacyPayment->failed_at,
            'cancelled' => $legacyPayment->updated_at,
            default => null,
        };
    }
};
