<?php

namespace Tests\Feature;

use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class DocumentNumberTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_requires_an_active_transaction(): void
    {
        DB::commit();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Document numbers can only be generated inside an active database transaction.');

        try {
            app(DocumentNumberService::class)->next('order');
        } finally {
            DB::beginTransaction();
        }
    }
}
