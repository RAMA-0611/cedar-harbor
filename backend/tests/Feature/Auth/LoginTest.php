<?php

namespace Tests\Feature\Auth;

use App\Models\User;

class LoginTest extends AuthTestCase
{

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.test',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'customer@example.test')
            ->assertJsonPath('user.role', 'CUSTOMER')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials_with_401(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_login_rejects_invalid_email_with_422(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'secret-password',
        ]);

        $response->assertStatus(422);
        $this->assertGuest();
    }
}
