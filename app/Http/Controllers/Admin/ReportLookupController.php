<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportLookupController extends Controller
{
    public function __construct(private ReportLookupService $lookups) {}

    public function customers(Request $request): JsonResponse
    {
        return $this->results($this->lookups->customers($this->search($request)));
    }

    public function products(Request $request): JsonResponse
    {
        return $this->results($this->lookups->products($this->search($request)));
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->results($this->lookups->categories($this->search($request)));
    }

    public function administrators(Request $request): JsonResponse
    {
        return $this->results($this->lookups->administrators($this->search($request)));
    }

    private function search(Request $request): string
    {
        return trim((string) $request->query('q'));
    }

    private function results(iterable $results): JsonResponse
    {
        return response()->json(['results' => collect($results)->values()]);
    }
}
