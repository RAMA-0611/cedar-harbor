<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\User;

class RegisterTest extends AuthTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Cliente Nuevo',
            'email' => 'nuevo.cliente@example.test',
            'phone' => '3001234567',
            'document_type' => 'CC',
            'document_number' => '1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_register_creates_customer_user_and_authenticates(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('user.email', 'nuevo.cliente@example.test')
            ->assertJsonPath('user.role', 'CUSTOMER')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('password');

        $user = User::query()->where('email', 'nuevo.cliente@example.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('CUSTOMER', $user->role);

        $customer = Customer::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($customer);
        $this->assertSame($customer->id, $response->json('user.customer_id'));
        $this->assertNotNull($user->fresh()->customer);

        $this->assertAuthenticatedAs($user);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->customer()->create([
            'email' => 'nuevo.cliente@example.test',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/register', $this->validPayload())
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertSame(1, User::query()->where('email', 'nuevo.cliente@example.test')->count());
    }

    public function test_register_rejects_password_confirmation_mismatch(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload([
            'password_confirmation' => 'different-password',
        ]))->assertStatus(422);

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo.cliente@example.test']);
    }

    public function test_register_ignores_role_escalation_attempts(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'role' => 'ADMIN',
            'is_admin' => true,
        ]));

        $response->assertCreated()
            ->assertJsonPath('user.role', 'CUSTOMER');

        $user = User::query()->where('email', 'nuevo.cliente@example.test')->firstOrFail();
        $this->assertSame('CUSTOMER', $user->role);
        $this->assertFalse($user->isAdmin());
        $this->assertDatabaseMissing('users', [
            'email' => 'nuevo.cliente@example.test',
            'role' => 'ADMIN',
        ]);
    }

    public function test_register_rolls_back_user_when_customer_creation_fails(): void
    {
        Customer::creating(function (): void {
            throw new \RuntimeException('forced customer failure');
        });

        $this->postJson('/api/auth/register', $this->validPayload())
            ->assertStatus(500);

        $this->assertDatabaseMissing('users', ['email' => 'nuevo.cliente@example.test']);
        $this->assertSame(0, Customer::query()->where('email', 'nuevo.cliente@example.test')->count());
        $this->assertGuest();
    }

    public function test_register_me_exposes_customer_id(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload())->assertCreated();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'CUSTOMER')
            ->assertJsonPath('user.email', 'nuevo.cliente@example.test');

        $this->assertNotNull($this->getJson('/api/auth/me')->json('user.customer_id'));
    }

    public function test_registered_customer_can_access_cart(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload())->assertCreated();

        $this->getJson('/api/cart')->assertOk();
    }
}
