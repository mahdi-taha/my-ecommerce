<?php

namespace Tests\Feature\Payments;

use App\Models\DocumentSequence;
use App\Services\PaymentNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PaymentNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_numbers_use_a_global_sequence_across_years(): void
    {
        Carbon::setTestNow('2026-12-31 23:59:59');
        $first = DB::transaction(fn () => app(PaymentNumberGenerator::class)->generate());

        Carbon::setTestNow('2027-01-01 00:00:00');
        $second = DB::transaction(fn () => app(PaymentNumberGenerator::class)->generate());

        $this->assertSame('PAY-2026-000001', $first);
        $this->assertSame('PAY-2027-000002', $second);
    }

    public function test_payment_number_increment_rolls_back_with_the_caller(): void
    {
        try {
            DB::transaction(function (): void {
                app(PaymentNumberGenerator::class)->generate();

                throw new RuntimeException('Force rollback.');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertSame(
            0,
            DocumentSequence::where('document_type', 'payment')->value('last_number')
        );
    }
}
