<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Celular colombiano: exactamente 10 dígitos, solo números, empieza por 3.
 */
class ColombianMobilePhone implements ValidationRule
{
    public const PATTERN = '/^3[0-9]{9}\z/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('El teléfono debe tener exactamente 10 dígitos, solo números y empezar por 3 (ejemplo: 3001234567).');
        }
    }
}
