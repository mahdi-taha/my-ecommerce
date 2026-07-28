<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use LogicException;

class DocumentNumberService
{
    public function next(string $documentType): int
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Document numbers can only be generated inside an active database transaction.');
        }

        $sequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            throw new LogicException("The document sequence [{$documentType}] has not been initialized.");
        }

        $sequence->last_number++;
        $sequence->saveOrFail();

        return $sequence->last_number;
    }
}
