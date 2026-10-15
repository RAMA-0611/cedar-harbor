<?php

namespace Tests\Feature\Catalog;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeaders([
            'Accept' => 'application/json',
        ]);
    }

    private function assertApiError(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['code', 'message', 'details', 'trace_id'])
            ->assertJsonPath('code', $code);

        $this->assertNotEmpty($response->json('trace_id'));
        $this->assertSame($response->json('trace_id'), $response->headers->get('X-Trace-Id'));
    }

    private function createCategory(array $overrides = []): Category
    {
        return Category::query()->create(array_merge([
            'name' => 'Cat '.Str::random(4),
            'slug' => 'cat-'.Str::lower(Str::random(8)),
            'is_active' => true,
        ], $overrides));
    }

    private function createProduct(?Category $category = null, array $overrides = []): Product
    {
        $category ??= $this->createCategory();

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => 'Catalog Product '.Str::random(4),
            'price' => '45000.00',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 24,
            'is_active' => true,
        ], $overrides));
    }

    public function test_list_products_returns_paginated_summaries(): void
    {
        $category = $this->createCategory();
        $this->createProduct($category, ['sku' => 'ROTOR-AAA', 'name' => 'Alpha Item']);
        $this->createProduct($category, ['sku' => 'ROTOR-BBB', 'name' => 'Beta Item']);

        $response = $this->getJson('/api/products?page=1&per_page=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'sku', 'name', 'unit_price', 'currency', 'available_quantity', 'tax_rate']],
                'meta' => ['page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('45000.00', $response->json('data.0.unit_price'));
        $this->assertSame('0.1900', $response->json('data.0.tax_rate'));
    }

    public function test_search_by_name_and_sku(): void
    {
        $category = $this->createCategory();
        $this->createProduct($category, ['sku' => 'FIND-SKU-9', 'name' => 'Hidden Name']);
        $this->createProduct($category, ['sku' => 'OTHER-SKU', 'name' => 'Rodamiento Visible']);

        $byName = $this->getJson('/api/products?q=Rodamiento');
        $byName->assertOk();
        $this->assertCount(1, $byName->json('data'));
        $this->assertSame('Rodamiento Visible', $byName->json('data.0.name'));

        $bySku = $this->getJson('/api/products?q=FIND-SKU');
        $bySku->assertOk();
        $this->assertCount(1, $bySku->json('data'));
        $this->assertSame('FIND-SKU-9', $bySku->json('data.0.sku'));
    }

    public function test_inactive_product_and_category_are_hidden(): void
    {
        $active = $this->createCategory(['slug' => 'active-cat']);
        $inactiveCat = $this->createCategory(['slug' => 'inactive-cat', 'is_active' => false]);

        $visible = $this->createProduct($active, ['sku' => 'VIS-1', 'name' => 'Visible']);
        $this->createProduct($active, ['sku' => 'HID-1', 'name' => 'Hidden Product', 'is_active' => false]);
        $this->createProduct($inactiveCat, ['sku' => 'HID-2', 'name' => 'Hidden Category Product']);

        $response = $this->getJson('/api/products');
        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame($visible->id, $response->json('data.0.id'));
    }

    public function test_available_quantity_considers_active_reservations_only(): void
    {
        $product = $this->createProduct(null, ['stock_quantity' => 20]);

        $cart = Cart::query()->create([
            'status' => 'ACTIVE',
            'currency' => 'COP',
        ]);
        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        StockReservation::query()->create([
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHour(),
        ]);

        StockReservation::query()->create([
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'status' => 'EXPIRED',
            'expires_at' => now()->subMinute(),
        ]);

        $list = $this->getJson('/api/products');
        $list->assertOk()->assertJsonPath('data.0.available_quantity', 15);

        $detail = $this->getJson('/api/products/'.$product->id);
        $detail->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('available_quantity', 15)
            ->assertJsonMissingPath('data');
    }

    public function test_product_detail_not_found_for_inactive(): void
    {
        $product = $this->createProduct(null, ['is_active' => false]);

        $this->assertApiError(
            $this->getJson('/api/products/'.$product->id),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }

    public function test_missing_product_returns_not_found(): void
    {
        $this->assertApiError(
            $this->getJson('/api/products/'.(string) Str::uuid()),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }

    public function test_invalid_uuid_returns_malformed_request(): void
    {
        $this->assertApiError(
            $this->getJson('/api/products/not-a-uuid'),
            400,
            'MALFORMED_REQUEST',
        );
    }

    public function test_malformed_page_and_per_page_return_400(): void
    {
        $this->assertApiError(
            $this->getJson('/api/products?page=abc'),
            400,
            'MALFORMED_REQUEST',
        );

        $this->assertApiError(
            $this->getJson('/api/products?per_page=101'),
            400,
            'MALFORMED_REQUEST',
        );
    }
}
