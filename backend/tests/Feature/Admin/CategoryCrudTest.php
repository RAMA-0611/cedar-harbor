<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryCrudTest extends AdminTestCase
{
    public function test_guest_gets_unauthenticated_api_error(): void
    {
        $this->assertApiError(
            $this->getJson('/api/admin/categories'),
            401,
            'UNAUTHENTICATED',
        );
    }

    public function test_customer_gets_forbidden_api_error(): void
    {
        $this->actingAsCustomerUser();

        $this->assertApiError(
            $this->getJson('/api/admin/categories'),
            403,
            'FORBIDDEN',
        );
    }

    public function test_category_crud_lifecycle(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/categories', [
            'name' => 'Rodamientos Sintéticos',
            'slug' => 'rodamientos-sinteticos',
            'description' => 'Categoría de prueba',
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Rodamientos Sintéticos')
            ->assertJsonPath('data.slug', 'rodamientos-sinteticos');

        $id = $create->json('data.id');
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('categories', ['id' => $id, 'slug' => 'rodamientos-sinteticos']);

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/admin/categories/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->patchJson("/api/admin/categories/{$id}", [
            'name' => 'Rodamientos Actualizados',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rodamientos Actualizados');

        $this->assertDatabaseHas('categories', [
            'id' => $id,
            'name' => 'Rodamientos Actualizados',
        ]);

        $this->deleteJson("/api/admin/categories/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_duplicate_slug_returns_validation_error(): void
    {
        $this->actingAsAdmin();
        $this->createCategory(['slug' => 'slug-duplicado']);

        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Otra',
            'slug' => 'slug-duplicado',
        ]);

        $this->assertApiError($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('slug', $response->json('details.errors') ?? []);
    }

    public function test_delete_category_with_products_returns_conflict(): void
    {
        $this->actingAsAdmin();
        $category = $this->createCategory();
        $this->createProduct($category);

        $response = $this->deleteJson("/api/admin/categories/{$category->id}");

        $this->assertApiError($response, 409, 'RESOURCE_IN_USE');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_missing_category_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->assertApiError(
            $this->getJson('/api/admin/categories/'.(string) Str::uuid()),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }
}
