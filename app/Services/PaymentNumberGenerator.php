<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class PaymentNumberGenerator
{
    private const DOCUMENT_TYPE = 'payment';

    public function __construct(private DocumentNumberService $documentNumberService) {}

    public function generate(): string
    {
        $timestamp = Carbon::now();
        $number = $this->documentNumberService->next(self::DOCUMENT_TYPE);

        return sprintf('PAY-%s-%06d', $timestamp->format('Y'), $number);
    }
}
