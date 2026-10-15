<?php

namespace Tests\Feature\Api;

use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class ApiErrorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api')->group(function () {
            Route::get('/__test/malformed', function () {
                throw new BadRequestHttpException('bad');
            });

            Route::get('/__test/unauthenticated', function () {
                throw new AuthenticationException('Unauthenticated.');
            });

            Route::get('/__test/forbidden', function () {
                throw new AuthorizationException('Forbidden.');
            });

            Route::get('/__test/missing', function () {
                abort(404);
            });

            Route::get('/__test/conflict', function () {
                throw ApiException::conflict(
                    'IDEMPOTENCY_CONFLICT',
                    'Idempotency key conflict.',
                    ['reason' => 'payload_mismatch'],
                );
            });

            Route::post('/__test/validation', function () {
                $validator = Validator::make(request()->all(), [
                    'email' => ['required', 'email'],
                    'name' => ['required', 'string'],
                ]);

                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }

                return response()->json(['ok' => true]);
            });

            Route::get('/__test/provider', function () {
                throw ApiException::providerFailure('Upstream payment provider failed.');
            });

            Route::get('/__test/boom', function () {
                throw new \RuntimeException('SENSITIVE_INTERNAL_DETAIL_SHOULD_NOT_LEAK');
            });

            Route::get('/__test/empty-details', function () {
                throw ApiException::conflict('IDEMPOTENCY_CONFLICT', 'Conflict without details.');
            });
        });
    }

    public function test_400_malformed_request_contract(): void
    {
        $response = $this->getJson('/api/__test/malformed');

        $this->assertApiError($response, 400, 'MALFORMED_REQUEST');
    }

    public function test_401_unauthenticated_contract(): void
    {
        $response = $this->getJson('/api/__test/unauthenticated');

        $this->assertApiError($response, 401, 'UNAUTHENTICATED');
    }

    public function test_403_forbidden_contract(): void
    {
        $response = $this->getJson('/api/__test/forbidden');

        $this->assertApiError($response, 403, 'FORBIDDEN');
    }

    public function test_404_resource_not_found_contract(): void
    {
        $response = $this->getJson('/api/__test/missing');

        $this->assertApiError($response, 404, 'RESOURCE_NOT_FOUND');
    }

    public function test_409_idempotency_conflict_contract(): void
    {
        $response = $this->getJson('/api/__test/conflict');

        $this->assertApiError($response, 409, 'IDEMPOTENCY_CONFLICT');
        $this->assertIsObject($response->json('details') ? (object) $response->json('details') : null);
        $this->assertSame('payload_mismatch', $response->json('details.reason'));
    }

    public function test_422_validation_error_includes_field_errors(): void
    {
        $response = $this->postJson('/api/__test/validation', [
            'email' => 'not-an-email',
        ]);

        $this->assertApiError($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('errors', $response->json('details'));
        $this->assertArrayHasKey('email', $response->json('details.errors'));
        $this->assertArrayHasKey('name', $response->json('details.errors'));
    }

    public function test_500_internal_error_hides_exception_even_when_debug_true(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/__test/boom');

        $this->assertApiError($response, 500, 'INTERNAL_ERROR');
        $this->assertSame('Unexpected server error.', $response->json('message'));
        $response->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('file')
            ->assertJsonMissingPath('line')
            ->assertJsonMissingPath('class');
        $this->assertStringNotContainsString(
            'SENSITIVE_INTERNAL_DETAIL_SHOULD_NOT_LEAK',
            $response->getContent()
        );
    }

    public function test_502_payment_provider_error_contract(): void
    {
        $response = $this->getJson('/api/__test/provider');

        $this->assertApiError($response, 502, 'PAYMENT_PROVIDER_ERROR');
    }

    public function test_details_serializes_as_object_when_empty(): void
    {
        $response = $this->getJson('/api/__test/empty-details');

        $this->assertApiError($response, 409, 'IDEMPOTENCY_CONFLICT');
        $raw = $response->json();
        $this->assertIsArray($raw['details']);
        $this->assertSame([], $raw['details']);
        // json_encode of (object)[] becomes {}
        $this->assertStringContainsString('"details":{}', $response->getContent());
        $this->assertStringNotContainsString('"details":[]', $response->getContent());
    }

    public function test_web_home_is_not_forced_into_api_error(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringNotContainsString('"code":', $response->getContent());
        $this->assertStringNotContainsString('trace_id', $response->getContent());
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
