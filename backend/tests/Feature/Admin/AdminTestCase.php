<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
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
    }

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin-'.Str::lower(Str::random(6)).'@example.test',
        ]);

        $this->actingAs($admin);

        return $admin;
    }

    protected function actingAsCustomerUser(): User
    {
        $customer = User::factory()->customer()->create([
            'email' => 'customer-'.Str::lower(Str::random(6)).'@example.test',
        ]);

        $this->actingAs($customer);

        return $customer;
    }

    protected function assertApiError(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['code', 'message', 'details', 'trace_id'])
            ->assertJsonPath('code', $code);

        $traceId = $response->json('trace_id');
        $this->assertNotEmpty($traceId);
        $this->assertSame($traceId, $response->headers->get('X-Trace-Id'));
    }

    protected function createCategory(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Cat '.Str::random(5),
            'slug' => 'cat-'.Str::lower(Str::random(8)),
            'description' => 'Synthetic category',
            'is_active' => true,
        ], $overrides));
    }

    protected function createProduct(?Category $category = null, array $overrides = []): Product
    {
        $category ??= $this->createCategory();

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => 'Product '.Str::random(5),
            'description' => 'Synthetic product',
            'brand' => 'SyntheticBrand',
            'condition' => 'Nuevo',
            'unit' => 'unidad',
            'price' => '10000.00',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 10,
            'is_active' => true,
        ], $overrides));
    }
}
