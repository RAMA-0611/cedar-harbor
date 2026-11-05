<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal money helpers using BCMath (no float).
 */
class Money
{
    public static function roundHalfUp(string $number, int $scale): string
    {
        if ($scale < 0) {
            throw new InvalidArgumentException('Scale must be >= 0.');
        }

        $negative = bccomp($number, '0', 20) < 0;
        $abs = $negative ? bcmul($number, '-1', 20) : $number;

        // Keep one extra digit to decide rounding.
        $padded = bcadd($abs, '0', $scale + 1);
        [$intPart, $fracPart] = array_pad(explode('.', $padded, 2), 2, '');
        $fracPart = str_pad($fracPart, $scale + 1, '0');

        $truncated = $scale === 0
            ? $intPart
            : $intPart.'.'.substr($fracPart, 0, $scale);
        $truncated = bcadd($truncated, '0', $scale);

        $nextDigit = (int) $fracPart[$scale];
        if ($nextDigit >= 5) {
            $unit = $scale === 0
                ? '1'
                : '0.'.str_repeat('0', max($scale - 1, 0)).'1';
            if ($scale === 1) {
                $unit = '0.1';
            }
            $truncated = bcadd($truncated, $unit, $scale);
        }

        return $negative ? bcmul($truncated, '-1', $scale) : $truncated;
    }

    public static function mul(string $left, string $right, int $scale = 2): string
    {
        return self::roundHalfUp(bcmul($left, $right, $scale + 4), $scale);
    }

    public static function add(string $left, string $right, int $scale = 2): string
    {
        return bcadd($left, $right, $scale);
    }

    public static function format(string $amount, int $scale = 2): string
    {
        return bcadd($amount, '0', $scale);
    }

    /**
     * Convert a decimal money string (2 places) to integer cents for Wompi.
     */
    public static function toCents(string $amount): int
    {
        $normalized = self::format($amount, 2);
        $parts = explode('.', $normalized, 2);
        $int = $parts[0];
        $frac = isset($parts[1]) ? str_pad($parts[1], 2, '0') : '00';
        $cents = $int.$frac;
        if (! preg_match('/^-?\d+$/', $cents)) {
            throw new InvalidArgumentException('Invalid money amount for cents conversion.');
        }

        return (int) $cents;
    }
}
