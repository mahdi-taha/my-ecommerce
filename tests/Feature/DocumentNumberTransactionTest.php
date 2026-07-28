<?php

namespace Tests\Feature;

use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use LogicException;
use Tests\TestCase;

class DocumentNumberTransactionTest extends TestCase
{
    use DatabaseMigrations;

    public function test_generation_requires_an_active_transaction(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Document numbers can only be generated inside an active database transaction.');

        app(DocumentNumberService::class)->next('order');
    }
}
