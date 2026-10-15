<?php

namespace Tests\Feature\Auth;

use App\Models\User;

class MeTest extends AuthTestCase
{

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user_without_secrets(): void
    {
        $user = User::factory()->admin()->create([
            'email' => 'admin@example.test',
            'password' => 'secret-password',
        ]);

        $this->actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'admin@example.test')
            ->assertJsonPath('user.role', 'ADMIN')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');
    }
}
