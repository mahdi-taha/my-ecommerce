<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class RefundNumberGenerator
{
    private const DOCUMENT_TYPE = 'refund';

    public function __construct(private DocumentNumberService $documentNumberService) {}

    public function generate(): string
    {
        return sprintf(
            'RFD-%s-%06d',
            Carbon::now()->format('Y'),
            $this->documentNumberService->next(self::DOCUMENT_TYPE)
        );
    }
}
