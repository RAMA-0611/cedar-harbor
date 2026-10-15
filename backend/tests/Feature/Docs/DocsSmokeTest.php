<?php

namespace Tests\Feature\Docs;

use Tests\TestCase;

class DocsSmokeTest extends TestCase
{
    public function test_docs_ui_is_available(): void
    {
        $response = $this->get('/docs');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $body = $response->getContent();
        $this->assertStringContainsString('swagger-ui', $body);
        $this->assertTrue(
            str_contains($body, '/docs/openapi.yaml') || str_contains($body, '\/docs\/openapi.yaml'),
            'Docs UI must reference the OpenAPI source URL.',
        );
    }

    public function test_openapi_source_is_available(): void
    {
        $response = $this->get('/docs/openapi.yaml');

        $response->assertOk();
        $this->assertStringContainsString('application/yaml', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('openapi:', $response->getContent());
        $this->assertStringContainsString('/api/products', $response->getContent());
    }
}
