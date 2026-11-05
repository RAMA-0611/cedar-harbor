<?php

declare(strict_types=1);

namespace App\Services\Payments\Wompi;

use RuntimeException;

final class WompiConfig
{
    public function env(): string
    {
        return (string) config('services.wompi.env', 'sandbox');
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('services.wompi.base_url', 'https://sandbox.wompi.co/v1'), '/');
    }

    public function checkoutUrl(): string
    {
        return rtrim((string) config('services.wompi.checkout_url', 'https://checkout.wompi.co/p/'), '/').'/';
    }

    public function publicKey(): string
    {
        return (string) config('services.wompi.public_key', '');
    }

    public function privateKey(): string
    {
        return (string) config('services.wompi.private_key', '');
    }

    public function integritySecret(): string
    {
        return (string) config('services.wompi.integrity_secret', '');
    }

    public function eventsSecret(): string
    {
        return (string) config('services.wompi.events_secret', '');
    }

    public function assertSandboxKeysConfigured(): void
    {
        $public = $this->publicKey();
        $private = $this->privateKey();
        $integrity = $this->integritySecret();

        if ($public === '' || $private === '' || $integrity === '') {
            throw new RuntimeException('Wompi sandbox credentials are not configured.');
        }

        if (! str_starts_with($public, 'pub_test_')) {
            throw new RuntimeException('Wompi public key must be a sandbox pub_test_ key.');
        }

        if (! str_starts_with($private, 'prv_test_')) {
            throw new RuntimeException('Wompi private key must be a sandbox prv_test_ key.');
        }
    }

    public function hasEventsSecret(): bool
    {
        return $this->eventsSecret() !== '';
    }
}
