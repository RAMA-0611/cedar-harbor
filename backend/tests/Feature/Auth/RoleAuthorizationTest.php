<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class RoleAuthorizationTest extends AuthTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:ADMIN'])
            ->get('/api/__test/admin-only', fn () => response()->json(['ok' => true]));
    }

    public function test_customer_cannot_access_admin_route(): void
    {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->getJson('/api/__test/admin-only')
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_route(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/__test/admin-only')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_guest_cannot_access_admin_route(): void
    {
        $this->getJson('/api/__test/admin-only')->assertUnauthorized();
    }
}
