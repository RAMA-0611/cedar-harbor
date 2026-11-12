<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

/**
 * Valida el número de documento según el tipo elegido (lista cerrada en DOCUMENT_TYPES).
 */
class DocumentNumberForType implements DataAwareRule, ValidationRule
{
    /** @var array<string, string> tipo => nombre */
    public const DOCUMENT_TYPES = [
        'CC' => 'Cédula de ciudadanía',
        'CE' => 'Cédula de extranjería',
        'NIT' => 'NIT',
        'PP' => 'Pasaporte',
    ];

    /** @var array<string, array{0: string, 1: string}> tipo => [patrón, mensaje] */
    private const RULES = [
        'CC' => ['/^[0-9]{6,10}\z/', 'La cédula de ciudadanía debe tener de 6 a 10 dígitos, solo números.'],
        'CE' => ['/^[0-9]{6,10}\z/', 'La cédula de extranjería debe tener de 6 a 10 dígitos, solo números.'],
        'NIT' => ['/^[0-9]{9,10}\z/', 'El NIT debe tener 9 o 10 dígitos, solo números y sin guion.'],
        'PP' => ['/^[A-Za-z0-9]{6,12}\z/', 'El pasaporte debe tener de 6 a 12 caracteres, solo letras y números.'],
    ];

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $typeKey = 'document_type') {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = Arr::get($this->data, $this->typeKey);
        if (! is_string($type) || ! isset(self::RULES[$type])) {
            // El tipo inválido ya lo reporta su propia regla.
            return;
        }

        [$pattern, $message] = self::RULES[$type];
        if (! is_string($value) || preg_match($pattern, $value) !== 1) {
            $fail($message);
        }
    }
}
