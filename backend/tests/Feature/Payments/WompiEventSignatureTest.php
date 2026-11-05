<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Services\Payments\Wompi\WompiEventSignature;
use Tests\TestCase;

class WompiEventSignatureTest extends TestCase
{
    private string $secret = 'test_events_secret_value';

    public function test_root_timestamp_valid_passes(): void
    {
        [$payload, $checksum] = $this->signedPayload();
        $this->assertTrue(WompiEventSignature::verify($payload, $checksum, $this->secret));
    }

    public function test_missing_root_timestamp_fails(): void
    {
        [$payload, $checksum] = $this->signedPayload();
        unset($payload['timestamp']);
        $this->assertFalse(WompiEventSignature::verify($payload, $checksum, $this->secret));
    }

    public function test_timestamp_only_inside_signature_fails(): void
    {
        [$payload, $checksum] = $this->signedPayload();
        $payload['signature']['timestamp'] = $payload['timestamp'];
        unset($payload['timestamp']);
        $this->assertFalse(WompiEventSignature::verify($payload, $checksum, $this->secret));
    }

    public function test_dynamic_property_valid_passes(): void
    {
        [$payload, $checksum] = $this->signedPayload(
            properties: ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
        );
        $this->assertTrue(WompiEventSignature::verify($payload, $checksum, $this->secret));
    }

    public function test_missing_property_path_fails(): void
    {
        [$payload] = $this->signedPayload();
        $payload['signature']['properties'] = ['transaction.id', 'transaction.missing_field'];
        $concat = 'tx-sig-1'.''.'1710000000'.$this->secret;
        // Even with a crafted checksum, missing path must fail before compare.
        $this->assertFalse(WompiEventSignature::verify($payload, hash('sha256', $concat), $this->secret));
    }

    public function test_incorrect_checksum_fails(): void
    {
        [$payload] = $this->signedPayload();
        $this->assertFalse(WompiEventSignature::verify($payload, 'deadbeef', $this->secret));
    }

    public function test_x_event_checksum_header_passes(): void
    {
        [$payload, $checksum] = $this->signedPayload();
        unset($payload['signature']['checksum']);
        $this->assertTrue(WompiEventSignature::verify($payload, $checksum, $this->secret));
    }

    public function test_signature_checksum_fallback_without_header_passes(): void
    {
        [$payload, $checksum] = $this->signedPayload();
        $payload['signature']['checksum'] = $checksum;
        $this->assertTrue(WompiEventSignature::verify($payload, '', $this->secret));
    }

    /**
     * @param  list<string>  $properties
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function signedPayload(
        array $properties = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'],
    ): array {
        $transaction = [
            'id' => 'tx-sig-1',
            'status' => 'APPROVED',
            'amount_in_cents' => 500000,
            'currency' => 'COP',
            'reference' => 'RTR-PAY-TEST',
        ];
        $timestamp = 1710000000;
        $concat = '';
        foreach ($properties as $path) {
            $parts = explode('.', $path);
            $cursor = ['transaction' => $transaction];
            foreach ($parts as $segment) {
                $cursor = $cursor[$segment];
            }
            $concat .= (string) $cursor;
        }
        $concat .= (string) $timestamp;
        $concat .= $this->secret;
        $checksum = hash('sha256', $concat);

        $payload = [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => $transaction,
            ],
            'environment' => 'test',
            'signature' => [
                'properties' => $properties,
                'checksum' => $checksum,
            ],
            'timestamp' => $timestamp,
            'sent_at' => '2024-03-09T12:00:00.000Z',
        ];

        return [$payload, $checksum];
    }
}
