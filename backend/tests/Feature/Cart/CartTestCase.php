<?php

namespace Tests\Feature\Cart;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class CartTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableCookieEncryption();

        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'Accept' => 'application/json',
        ]);
    }

    protected function assertApiError(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)
            ->assertJsonStructure(['code', 'message', 'details', 'trace_id'])
            ->assertJsonPath('code', $code);

        $this->assertNotEmpty($response->json('trace_id'));
        $this->assertSame($response->json('trace_id'), $response->headers->get('X-Trace-Id'));
    }

    /**
     * @return array{user: User, customer: Customer}
     */
    protected function createCustomerActor(array $userOverrides = [], array $customerOverrides = []): array
    {
        $user = User::factory()->customer()->create(array_merge([
            'email' => 'customer-'.Str::lower(Str::random(6)).'@example.test',
        ], $userOverrides));

        $customer = Customer::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Cliente Sintetico',
            'email' => $user->email,
            'phone' => '+57-300-000-0000',
            'document_type' => 'CC',
            'document_number' => (string) random_int(1000000000, 1999999999),
        ], $customerOverrides));

        return compact('user', 'customer');
    }

    protected function actingAsCustomerWithProfile(): array
    {
        $pair = $this->createCustomerActor();
        $this->actingAs($pair['user']);

        return $pair;
    }

    protected function createCategory(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Cat '.Str::random(4),
            'slug' => 'cat-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ], $overrides));
    }

    protected function createProduct(?Category $category = null, array $overrides = []): Product
    {
        $category ??= $this->createCategory();

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => 'Producto carrito '.Str::random(4),
            'price' => '10000.00',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 10,
            'is_active' => true,
        ], $overrides));
    }
}
