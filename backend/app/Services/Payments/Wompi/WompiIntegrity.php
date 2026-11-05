<?php

declare(strict_types=1);

namespace App\Services\Payments\Wompi;

final class WompiIntegrity
{
    /**
     * Web Checkout integrity signature:
     * SHA256(reference + amount_in_cents + currency + integrity_secret)
     */
    public static function sign(string $reference, int $amountInCents, string $currency, string $integritySecret): string
    {
        $payload = $reference.(string) $amountInCents.$currency.$integritySecret;

        return hash('sha256', $payload);
    }
}
