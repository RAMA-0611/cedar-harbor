<?php

declare(strict_types=1);

namespace App\Services\Payments\Wompi;

final class WompiEventSignature
{
    /**
     * Validate Wompi event checksum using dynamic signature.properties.
     *
     * Official event shape places `timestamp` at the payload ROOT (not under signature).
     * Concatenation: property values (in order) + root timestamp + events secret → SHA256.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function verify(array $payload, string $checksumHeader, string $eventsSecret): bool
    {
        $signature = $payload['signature'] ?? null;
        if (! is_array($signature)) {
            return false;
        }

        $properties = $signature['properties'] ?? null;
        if (! is_array($properties) || $properties === []) {
            return false;
        }

        if (! array_key_exists('timestamp', $payload) || $payload['timestamp'] === null || $payload['timestamp'] === '') {
            return false;
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data)) {
            return false;
        }

        $concat = '';
        foreach ($properties as $propertyPath) {
            if (! is_string($propertyPath) || $propertyPath === '') {
                return false;
            }
            $value = self::resolvePath($data, $propertyPath);
            if ($value === null) {
                return false;
            }
            $concat .= (string) $value;
        }

        $concat .= (string) $payload['timestamp'];
        $concat .= $eventsSecret;
        $expected = hash('sha256', $concat);

        $provided = self::resolveProvidedChecksum($payload, $checksumHeader);
        if ($provided === '') {
            return false;
        }

        return hash_equals(strtolower($expected), strtolower($provided));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function resolveProvidedChecksum(array $payload, string $checksumHeader): string
    {
        $provided = trim($checksumHeader);
        if ($provided !== '') {
            return $provided;
        }

        $signature = $payload['signature'] ?? null;
        if (is_array($signature) && isset($signature['checksum']) && is_string($signature['checksum'])) {
            return trim($signature['checksum']);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolvePath(array $data, string $path): mixed
    {
        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }

        if (is_scalar($cursor) || $cursor === null) {
            return $cursor;
        }

        return null;
    }
}
