<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use App\Support\ApiErrorResponse;

class ApiException extends Exception
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function conflict(string $code, string $message, array $details = []): self
    {
        return new self(409, $code, $message, $details);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function providerFailure(string $message = 'Payment provider error.', array $details = []): self
    {
        return new self(502, 'PAYMENT_PROVIDER_ERROR', $message, $details);
    }

    public function render(): JsonResponse
    {
        return ApiErrorResponse::make(
            $this->status,
            $this->errorCode,
            $this->getMessage(),
            $this->details,
        );
    }
}
