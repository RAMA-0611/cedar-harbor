<?php

namespace Tests\Feature\Auth;

use App\Models\User;

class LogoutTest extends AuthTestCase
{
    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'customer@example.test',
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/auth/logout')->assertNoContent();

        // Fresh guards so the next request rehydrates only from the session cookie
        // (mirrors a new browser request after logout cleared the session).
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
