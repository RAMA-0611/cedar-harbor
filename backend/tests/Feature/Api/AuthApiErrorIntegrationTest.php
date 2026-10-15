<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthApiErrorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableCookieEncryption();

        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
        ]);

        Route::middleware(['auth:sanctum', 'role:ADMIN'])
            ->get('/api/__test/admin-only-apierror', fn () => response()->json(['ok' => true]));
    }

    public function test_me_without_auth_returns_unauthenticated_api_error(): void
    {
        $response = $this->getJson('/api/auth/me');

        $this->assertApiError($response, 401, 'UNAUTHENTICATED');
        $this->assertStringContainsString('"details":{}', $response->getContent());
    }

    public function test_login_with_wrong_password_returns_unauthenticated_api_error(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@example.test',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'customer@example.test',
            'password' => 'wrong-password',
        ]);

        $this->assertApiError($response, 401, 'UNAUTHENTICATED');
        $this->assertGuest();
    }

    public function test_login_with_invalid_email_returns_validation_api_error(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'secret-password',
        ]);

        $this->assertApiError($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('email', $response->json('details.errors') ?? []);
    }

    public function test_customer_against_admin_role_returns_forbidden_api_error(): void
    {
        $user = User::factory()->customer()->create();

        $response = $this->actingAs($user)->getJson('/api/__test/admin-only-apierror');

        $this->assertApiError($response, 403, 'FORBIDDEN');
    }

    public function test_admin_against_admin_role_is_allowed(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->getJson('/api/__test/admin-only-apierror')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    private function assertApiError($response, int $status, string $code): void
    {
        $response->assertStatus($status)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'code',
                'message',
                'details',
                'trace_id',
            ])
            ->assertJsonPath('code', $code);

        $traceId = $response->json('trace_id');
        $this->assertNotEmpty($traceId);
        $this->assertSame($traceId, $response->headers->get('X-Trace-Id'));
    }
}
