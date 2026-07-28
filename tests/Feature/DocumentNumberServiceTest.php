<?php

namespace Tests\Feature;

use App\Models\DocumentSequence;
use App\Services\DocumentNumberService;
use App\Services\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_sequence_is_initialized_on_a_fresh_database(): void
    {
        $this->assertDatabaseHas('document_sequences', [
            'document_type' => 'order',
            'last_number' => 0,
        ]);
    }

    public function test_numbers_are_sequential_and_use_the_captured_year(): void
    {
        Carbon::setTestNow('2026-12-31 23:59:59');

        $first = DB::transaction(fn () => app(OrderNumberGenerator::class)->generate());
        $second = DB::transaction(fn () => app(OrderNumberGenerator::class)->generate());

        $this->assertSame('ORD-2026-000001', $first);
        $this->assertSame('ORD-2026-000002', $second);
    }

    public function test_global_sequence_does_not_reset_across_years(): void
    {
        Carbon::setTestNow('2026-12-31 23:59:59');
        $first = DB::transaction(fn () => app(OrderNumberGenerator::class)->generate());

        Carbon::setTestNow('2027-01-01 00:00:00');
        $second = DB::transaction(fn () => app(OrderNumberGenerator::class)->generate());

        $this->assertSame('ORD-2026-000001', $first);
        $this->assertSame('ORD-2027-000002', $second);
    }

    public function test_sequence_increment_rolls_back_with_the_caller_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                app(DocumentNumberService::class)->next('order');

                throw new RuntimeException('Force rollback.');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertSame(0, DocumentSequence::where('document_type', 'order')->value('last_number'));
    }

    public function test_uninitialized_document_type_is_rejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The document sequence [payment] has not been initialized.');

        DB::transaction(fn () => app(DocumentNumberService::class)->next('payment'));
    }

    public function test_legacy_initializer_rejects_malformed_numbers_without_changing_sequence(): void
    {
        DB::table('orders')->insert($this->legacyOrder('INVALID-ORDER'));
        DocumentSequence::where('document_type', 'order')->update(['last_number' => 7]);

        $migration = require database_path('migrations/2026_07_28_000002_initialize_order_document_sequence.php');

        try {
            $migration->up();
            $this->fail('A malformed legacy order number was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('INVALID-ORDER', $exception->getMessage());
        }

        $this->assertSame(7, DocumentSequence::where('document_type', 'order')->value('last_number'));
    }

    public function test_legacy_initializer_accepts_both_formats_and_uses_highest_final_component(): void
    {
        DB::table('orders')->insert([
            $this->legacyOrder('ORD-00000017'),
            $this->legacyOrder('ORD-2025-000042'),
        ]);

        $migration = require database_path('migrations/2026_07_28_000002_initialize_order_document_sequence.php');
        $migration->up();

        $this->assertSame(42, DocumentSequence::where('document_type', 'order')->value('last_number'));
    }

    public function test_database_locking_concurrency_is_deferred_on_sqlite(): void
    {
        $this->markTestSkipped(
            DB::getDriverName() === 'sqlite'
                ? 'SQLite does not provide row-level lockForUpdate concurrency semantics.'
                : 'Run the dedicated parallel database concurrency check for this database driver.'
        );
    }

    private function legacyOrder(string $orderNumber): array
    {
        return [
            'order_number' => $orderNumber,
            'customer_email' => 'legacy@example.com',
            'customer_first_name' => 'Legacy',
            'customer_last_name' => 'Order',
            'locale' => 'en',
            'currency_code' => 'USD',
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'unfulfilled',
            'payment_method' => 'cash_on_delivery',
            'subtotal' => 1,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 1,
            'placed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
