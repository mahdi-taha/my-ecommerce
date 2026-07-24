<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignCorrelationId
{
    private const HEADER = 'X-Correlation-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->resolveCorrelationId($request);

        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext(['correlation_id' => $correlationId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    private function resolveCorrelationId(Request $request): string
    {
        $correlationId = $request->header(self::HEADER);

        if (is_string($correlationId)
            && preg_match('/\A[A-Za-z0-9._-]{1,100}\z/', $correlationId) === 1) {
            return $correlationId;
        }

        return (string) Str::uuid();
    }
}
