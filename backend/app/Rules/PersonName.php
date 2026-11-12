<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nombre de persona: letras (con tildes y ñ), espacios, apóstrofo, punto y guion. 2 a 100 caracteres.
 */
class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match("/^\\p{L}[\\p{L}\\p{M} '.-]{1,99}\z/u", $value) !== 1) {
            $fail('El nombre solo puede tener letras, espacios, apóstrofo, punto y guion (de 2 a 100 caracteres), sin números ni otros símbolos.');
        }
    }
}
