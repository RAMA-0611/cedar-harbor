<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Contraseña: 8 a 72 caracteres, al menos una letra y un número.
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || strlen($value) < 8
            || strlen($value) > 72
            || preg_match('/\p{L}/u', $value) !== 1
            || preg_match('/[0-9]/', $value) !== 1) {
            $fail('La contraseña debe tener de 8 a 72 caracteres, con al menos una letra y un número.');
        }
    }
}
