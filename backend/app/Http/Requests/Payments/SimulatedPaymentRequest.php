<?php

declare(strict_types=1);

namespace App\Http\Requests\Payments;

use App\Rules\PersonName;
use App\Services\Payments\Simulated\SimulatedCards;
use Illuminate\Foundation\Http\FormRequest;

class SimulatedPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $number = $this->input('card_number');
        if (is_string($number)) {
            $this->merge(['card_number' => preg_replace('/[\s-]/', '', $number)]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'card_holder' => ['required', 'string', new PersonName],
            'card_number' => [
                'required', 'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! SimulatedCards::luhnValid($value)) {
                        $fail('El número de tarjeta no es válido (13 a 19 dígitos).');
                    }
                },
            ],
            'expiry' => [
                'required', 'string', 'regex:/^(0[1-9]|1[0-2])\/[0-9]{2}\z/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || preg_match('/^(\d{2})\/(\d{2})$/', $value, $m) !== 1) {
                        return;
                    }
                    $end = \Carbon\CarbonImmutable::createFromDate(2000 + (int) $m[2], (int) $m[1], 1)->endOfMonth();
                    if ($end->isPast()) {
                        $fail('La tarjeta está vencida.');
                    }
                },
            ],
            'cvc' => ['required', 'string', 'regex:/^[0-9]{3,4}\z/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'expiry.regex' => 'La fecha debe tener el formato MM/AA (ejemplo: 12/30).',
            'cvc.regex' => 'El CVC debe tener 3 o 4 dígitos.',
        ];
    }
}
