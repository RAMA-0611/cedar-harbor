<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $details
     */
    public static function make(
        int $status,
        string $code,
        string $message,
        array $details = [],
        ?string $traceId = null,
    ): JsonResponse {
        $traceId ??= (string) Str::uuid();

        $payload = [
            'code' => $code,
            'message' => $message,
            'details' => (object) $details,
            'trace_id' => $traceId,
        ];

        return response()
            ->json($payload, $status)
            ->header('X-Trace-Id', $traceId);
    }
}
