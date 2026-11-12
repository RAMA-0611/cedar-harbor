<?php

declare(strict_types=1);

namespace App\Services\Payments\Simulated;

/**
 * Tarjetas de prueba de la pasarela simulada. El resultado depende SOLO del número de tarjeta.
 * Ningún dato de tarjeta se guarda: solo se conserva el resultado y los últimos 4 dígitos.
 */
final class SimulatedCards
{
    public const APPROVED = 'APPROVED';
    public const DECLINED = 'DECLINED';
    public const INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    public const ERROR = 'ERROR';
    public const PENDING = 'PENDING';

    /** @var array<string, array{outcome: string, label: string}> */
    public const TEST_CARDS = [
        '4242424242424242' => ['outcome' => self::APPROVED, 'label' => 'Pago aprobado'],
        '4000000000000002' => ['outcome' => self::DECLINED, 'label' => 'Tarjeta rechazada por el banco'],
        '4000000000009995' => ['outcome' => self::INSUFFICIENT_FUNDS, 'label' => 'Fondos insuficientes'],
        '4000000000000119' => ['outcome' => self::ERROR, 'label' => 'Error al procesar el pago'],
        '4000000000003220' => ['outcome' => self::PENDING, 'label' => 'Pendiente de verificación (3-D Secure)'],
    ];

    public static function luhnValid(string $number): bool
    {
        if (preg_match('/^[0-9]{13,19}$/', $number) !== 1) {
            return false;
        }
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $d = (int) $number[$i];
            if ($alt) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    /** Cualquier otra tarjeta con Luhn válido se aprueba, igual que en una pasarela sandbox genérica. */
    public static function outcomeFor(string $number): string
    {
        return self::TEST_CARDS[$number]['outcome'] ?? self::APPROVED;
    }
}
