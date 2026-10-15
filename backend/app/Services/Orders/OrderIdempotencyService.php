<?php

namespace App\Services\Orders;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\QueryException;
use JsonException;

/**
 * Reusable idempotency helpers for POST /api/orders (fingerprint + replay + UNIQUE race).
 */
class OrderIdempotencyService
{
    /**
     * Trim outer whitespace, then treat the key as opaque.
     * Missing / empty / wrong length → 400 MALFORMED_REQUEST (not 422).
     */
    public function validateKey(?string $key): string
    {
        $normalized = trim((string) $key);
        $length = mb_strlen($normalized);

        if ($normalized === '' || $length < 8 || $length > 128) {
            throw new ApiException(
                400,
                'MALFORMED_REQUEST',
                'Idempotency-Key header is required and must be 8-128 characters.',
                ['header' => 'Idempotency-Key'],
            );
        }

        return $normalized;
    }

    /**
     * SHA-256 hex (64 chars) over customer_id + canonical CheckoutRequest fields.
     * Does not include cart lines (cart may already be CONVERTED on retry).
     *
     * @param  array<string, mixed>  $checkoutPayload
     */
    public function fingerprint(string $customerId, array $checkoutPayload): string
    {
        $envelope = [
            'customer_id' => $customerId,
            'request' => $this->normalizeCheckoutPayload($checkoutPayload),
        ];

        return hash('sha256', $this->canonicalJson($envelope));
    }

    /**
     * Lookup by idempotency_key.
     * Same customer + same hash → Order (with items).
     * Other customer or different hash → 409 IDEMPOTENCY_CONFLICT.
     * Missing → null.
     */
    public function findReplay(Customer $customer, string $key, string $hash): ?Order
    {
        $existing = Order::query()
            ->where('idempotency_key', $key)
            ->with('items')
            ->first();

        if ($existing === null) {
            return null;
        }

        $this->assertReplayMatch($customer, $existing, $hash);

        return $existing;
    }

    /**
     * After a UNIQUE(idempotency_key) collision: re-read and replay or conflict.
     */
    public function resolveAfterUniqueViolation(Customer $customer, string $key, string $hash): Order
    {
        $existing = Order::query()
            ->where('idempotency_key', $key)
            ->with('items')
            ->first();

        if ($existing === null) {
            throw new ApiException(
                409,
                'IDEMPOTENCY_CONFLICT',
                'Idempotency-Key collision could not be resolved.',
                ['reason' => 'missing_after_unique_violation'],
            );
        }

        $this->assertReplayMatch($customer, $existing, $hash);

        return $existing;
    }

    /**
     * Detect UNIQUE violation on orders.idempotency_key (SQLSTATE 23505),
     * without relying on driver-specific APIs beyond the SQLSTATE.
     */
    public function isIdempotencyKeyUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        if ($sqlState !== '23505') {
            return false;
        }

        $haystack = strtolower($exception->getMessage());

        return str_contains($haystack, 'idempotency_key');
    }

    /**
     * @param  array<string, mixed>  $checkoutPayload
     * @return array{customer: array<string, string>, delivery: array{address: ?string, mode: ?string}}
     */
    public function normalizeCheckoutPayload(array $checkoutPayload): array
    {
        $customer = is_array($checkoutPayload['customer'] ?? null)
            ? $checkoutPayload['customer']
            : [];
        $delivery = is_array($checkoutPayload['delivery'] ?? null)
            ? $checkoutPayload['delivery']
            : [];

        $mode = isset($delivery['mode']) ? (string) $delivery['mode'] : null;
        $address = array_key_exists('address', $delivery) ? $delivery['address'] : null;

        if ($mode === 'PICKUP') {
            $address = null;
        } elseif (is_string($address)) {
            $trimmed = trim($address);
            $address = $trimmed === '' ? null : $trimmed;
        } elseif ($address !== null) {
            $address = (string) $address;
        }

        return [
            'customer' => [
                'document_number' => (string) ($customer['document_number'] ?? ''),
                'document_type' => (string) ($customer['document_type'] ?? ''),
                'email' => (string) ($customer['email'] ?? ''),
                'name' => (string) ($customer['name'] ?? ''),
                'phone' => (string) ($customer['phone'] ?? ''),
            ],
            'delivery' => [
                'address' => $address,
                'mode' => $mode,
            ],
        ];
    }

    /**
     * @throws ApiException
     */
    private function assertReplayMatch(Customer $customer, Order $existing, string $hash): void
    {
        if ((string) $existing->customer_id !== (string) $customer->id) {
            throw ApiException::conflict(
                'IDEMPOTENCY_CONFLICT',
                'Idempotency-Key belongs to another customer.',
                ['reason' => 'customer_mismatch'],
            );
        }

        if ((string) $existing->idempotency_request_hash !== $hash) {
            throw ApiException::conflict(
                'IDEMPOTENCY_CONFLICT',
                'Idempotency-Key was reused with a different payload.',
                ['reason' => 'payload_mismatch'],
            );
        }
    }

    /**
     * @param  mixed  $value
     *
     * @throws JsonException
     */
    private function canonicalJson(mixed $value): string
    {
        return json_encode(
            $this->sortKeysRecursive($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }

    private function sortKeysRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $sorted = [];

        foreach ($value as $key => $child) {
            $sorted[$key] = $this->sortKeysRecursive($child);
        }

        if (! $isList) {
            ksort($sorted, SORT_STRING);
        }

        return $sorted;
    }
}
