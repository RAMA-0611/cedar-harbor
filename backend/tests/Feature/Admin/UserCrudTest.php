<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\User;

class UserCrudTest extends AdminTestCase
{
    public function test_user_crud_lifecycle(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/users', [
            'name' => 'Usuario Sintetico',
            'email' => 'user.crud@example.test',
            'password' => 'password123',
            'role' => 'CUSTOMER',
            'phone' => '3001234567',
            'document_type' => 'CC',
            'document_number' => '1234567890',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.email', 'user.crud@example.test')
            ->assertJsonPath('data.role', 'CUSTOMER')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $id = $create->json('data.id');
        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'user.crud@example.test']);

        $stored = User::query()->findOrFail($id);
        $this->assertNotSame('password123', $stored->password);
        $this->assertTrue(password_verify('password123', $stored->password) || \Illuminate\Support\Facades\Hash::check('password123', $stored->password));

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/admin/users/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonMissingPath('data.password');

        $this->patchJson("/api/admin/users/{$id}", [
            'name' => 'Usuario Actualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Usuario Actualizado');

        $this->deleteJson("/api/admin/users/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $id]);
    }

    public function test_duplicate_email_and_invalid_role_return_validation_error(): void
    {
        $this->actingAsAdmin();
        User::factory()->customer()->create(['email' => 'taken@example.test']);

        $dup = $this->postJson('/api/admin/users', [
            'name' => 'Dup',
            'email' => 'taken@example.test',
            'password' => 'password123',
            'role' => 'CUSTOMER',
        ]);
        $this->assertApiError($dup, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('email', $dup->json('details.errors') ?? []);

        $role = $this->postJson('/api/admin/users', [
            'name' => 'Bad Role',
            'email' => 'badrole@example.test',
            'password' => 'password123',
            'role' => 'SUPERUSER',
        ]);
        $this->assertApiError($role, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('role', $role->json('details.errors') ?? []);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->actingAsAdmin();

        $this->assertApiError(
            $this->deleteJson("/api/admin/users/{$admin->id}"),
            403,
            'FORBIDDEN',
        );
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_delete_user_nullifies_customer_user_id(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->customer()->create([
            'email' => 'linked@example.test',
        ]);

        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'name' => 'Cliente Vinculado',
            'email' => 'linked-customer@example.test',
            'phone' => '300',
            'document_type' => 'CC',
            'document_number' => '999',
        ]);

        $this->deleteJson("/api/admin/users/{$user->id}")->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => null,
            'name' => 'Cliente Vinculado',
        ]);
    }

    public function test_admin_created_customer_gets_customer_profile(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/users', [
            'name' => 'Cliente Panel',
            'email' => 'cliente.panel@example.test',
            'password' => 'password123',
            'role' => 'CUSTOMER',
            'phone' => '3151112233',
            'document_type' => 'CE',
            'document_number' => '9876543',
        ])->assertCreated();

        $user = User::query()->findOrFail($create->json('data.id'));
        $this->assertNotNull($user->customer);
        $this->assertSame('cliente.panel@example.test', $user->customer->email);
        $this->assertSame('3151112233', $user->customer->phone);
        $this->assertSame('CE', $user->customer->document_type);
        $this->assertSame('9876543', $user->customer->document_number);
    }

    public function test_admin_cannot_create_customer_without_phone_and_document(): void
    {
        $this->actingAsAdmin();

        $res = $this->postJson('/api/admin/users', [
            'name' => 'Sin Perfil',
            'email' => 'sin.perfil@example.test',
            'password' => 'password123',
            'role' => 'CUSTOMER',
        ]);

        $this->assertApiError($res, 422, 'VALIDATION_ERROR');
        $errors = $res->json('details.errors') ?? [];
        $this->assertArrayHasKey('phone', $errors);
        $this->assertArrayHasKey('document_type', $errors);
        $this->assertArrayHasKey('document_number', $errors);

    }

    public function test_admin_created_admin_does_not_get_customer_profile(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/users', [
            'name' => 'Otro Admin',
            'email' => 'otro.admin@example.test',
            'password' => 'password123',
            'role' => 'ADMIN',
        ])->assertCreated();

        $this->assertDatabaseMissing('customers', ['user_id' => $create->json('data.id')]);
    }

    public function test_changing_role_to_customer_creates_missing_profile(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->create(['role' => 'ADMIN', 'email' => 'cambio.rol@example.test']);

        $this->patchJson("/api/admin/users/{$user->id}", ['role' => 'CUSTOMER'])->assertOk();

        $this->assertDatabaseHas('customers', ['user_id' => $user->id]);
    }
}
