<?php

declare(strict_types=1);

namespace Tests\Feature\Validation;

use App\Rules\ColombianMobilePhone;
use App\Rules\DocumentNumberForType;
use App\Rules\PersonName;
use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Auth\AuthTestCase;

class FieldValidationTest extends AuthTestCase
{
    /** @return array<string, array{string, bool}> */
    public static function phones(): array
    {
        return [
            'válido 3001234567' => ['3001234567', true],
            'válido 3999999999' => ['3999999999', true],
            'empieza por 2' => ['2001234567', false],
            'empieza por 6' => ['6012345678', false],
            '9 dígitos' => ['300123456', false],
            '11 dígitos' => ['30012345678', false],
            'con letras' => ['300123456a', false],
            'solo letras' => ['abcdefghij', false],
            'con +57' => ['+573001234567', false],
            'con guiones' => ['300-123-4567', false],
            'con espacios' => ['300 123 4567', false],
            'con paréntesis' => ['(300)1234567', false],
            'negativo' => ['-300123456', false],
            'decimal' => ['3001234.67', false],
            'salto de línea final' => ["3001234567\n", false],
            'vacío' => ['', false],
        ];
    }

    #[DataProvider('phones')]
    public function test_phone_rule(string $value, bool $valid): void
    {
        $v = Validator::make(['phone' => $value], ['phone' => ['required', new ColombianMobilePhone]]);
        $this->assertSame($valid, $v->passes(), "phone=[$value]");
    }

    /** @return array<string, array{string, string, bool}> */
    public static function documents(): array
    {
        return [
            'CC válida' => ['CC', '1234567890', true],
            'CC 6 dígitos' => ['CC', '123456', true],
            'CC con letras' => ['CC', '12345abc', false],
            'CC corta' => ['CC', '12345', false],
            'CC con puntos' => ['CC', '1.234.567', false],
            'CE válida' => ['CE', '9876543', true],
            'NIT válido' => ['NIT', '900123456', true],
            'NIT con guion' => ['NIT', '900123456-7', false],
            'PP alfanumérico' => ['PP', 'AB123456', true],
            'PP con símbolo' => ['PP', 'AB-12345', false],
            'tipo libre' => ['XX', '1234567', false],
            'tipo en minúscula' => ['cc', '1234567', false],
        ];
    }

    #[DataProvider('documents')]
    public function test_document_rules(string $type, string $number, bool $valid): void
    {
        $v = Validator::make(
            ['document_type' => $type, 'document_number' => $number],
            [
                'document_type' => ['required', \Illuminate\Validation\Rule::in(array_keys(DocumentNumberForType::DOCUMENT_TYPES))],
                'document_number' => ['required', new DocumentNumberForType('document_type')],
            ],
        );
        $this->assertSame($valid, $v->passes(), "$type $number");
    }

    /** @return array<string, array{string, bool}> */
    public static function names(): array
    {
        return [
            'simple' => ['Juan', true],
            'con tilde y ñ' => ['María Peña', true],
            'apóstrofo' => ["D'Angelo", true],
            'con número' => ['Juan2', false],
            'con símbolo' => ['Juan@', false],
            'una letra' => ['J', false],
            'solo espacios' => ['  ', false],
            'etiqueta html' => ['<b>x</b>', false],
        ];
    }

    #[DataProvider('names')]
    public function test_name_rule(string $value, bool $valid): void
    {
        $v = Validator::make(['name' => $value], ['name' => ['required', 'string', new PersonName]]);
        $this->assertSame($valid, $v->passes(), "name=[$value]");
    }

    public function test_password_rule(): void
    {
        foreach ([['abc12345', true], ['sololetras', false], ['12345678', false], ['a1', false]] as [$pwd, $ok]) {
            $v = Validator::make(['p' => $pwd], ['p' => [new StrongPassword]]);
            $this->assertSame($ok, $v->passes(), $pwd);
        }
    }

    public function test_register_endpoint_reports_each_invalid_field(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'Ana1',
            'email' => 'no-es-email',
            'phone' => '2001234567',
            'document_type' => 'XX',
            'document_number' => 'abc',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ]);

        $res->assertStatus(422);
        $errors = $res->json('details.errors') ?? [];
        foreach (['name', 'email', 'phone', 'document_type', 'password'] as $field) {
            $this->assertArrayHasKey($field, $errors, $field);
        }
    }

    public function test_register_rejects_phone_with_letters_and_symbols(): void
    {
        foreach (['30012345ab', '+573001234567', '300-123-4567'] as $phone) {
            $this->postJson('/api/auth/register', [
                'name' => 'Cliente Valido',
                'email' => 'v'.md5($phone).'@example.test',
                'phone' => $phone,
                'document_type' => 'CC',
                'document_number' => '1234567890',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertStatus(422)->assertJsonPath('details.errors.phone.0', fn ($m) => is_string($m));
        }
    }
}
