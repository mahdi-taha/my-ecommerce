<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class OrderNumberGenerator
{
    private const DOCUMENT_TYPE = 'order';

    public function __construct(private DocumentNumberService $documentNumberService) {}

    public function generate(): string
    {
        $timestamp = Carbon::now();
        $number = $this->documentNumberService->next(self::DOCUMENT_TYPE);

        return sprintf('ORD-%s-%06d', $timestamp->format('Y'), $number);
    }
}
