<?php

declare(strict_types=1);

namespace App\Services\Payments\Wompi;

final class WompiEventSignature
{
    /**
     * Validate Wompi event checksum using dynamic signature.properties.
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
        $timestamp = $signature['timestamp'] ?? null;
        if (! is_array($properties) || $timestamp === null || $timestamp === '') {
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

        $concat .= (string) $timestamp;
        $concat .= $eventsSecret;
        $expected = hash('sha256', $concat);

        $provided = trim($checksumHeader);
        if ($provided === '' && isset($signature['checksum']) && is_string($signature['checksum'])) {
            $provided = $signature['checksum'];
        }

        if ($provided === '') {
            return false;
        }

        return hash_equals(strtolower($expected), strtolower($provided));
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
