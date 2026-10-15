<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Str;

class ProductCrudTest extends AdminTestCase
{
    public function test_product_crud_lifecycle(): void
    {
        $this->actingAsAdmin();
        $category = $this->createCategory();

        $create = $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'SKU-CRUD-001',
            'name' => 'Producto CRUD',
            'description' => 'Desc',
            'brand' => 'Marca',
            'condition' => 'Nuevo',
            'unit' => 'unidad',
            'price' => '25000.00',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.sku', 'SKU-CRUD-001')
            ->assertJsonPath('data.price', '25000.00');

        $id = $create->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $id, 'sku' => 'SKU-CRUD-001']);

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/admin/products/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->patchJson("/api/admin/products/{$id}", [
            'name' => 'Producto Actualizado',
            'stock_quantity' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Producto Actualizado')
            ->assertJsonPath('data.stock_quantity', 8);

        $this->deleteJson("/api/admin/products/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('products', ['id' => $id]);
    }

    public function test_admin_product_preserves_decimal18_precision_as_string(): void
    {
        $this->actingAsAdmin();

        // DECIMAL(18,2) upper bound — would lose digits if cast through float.
        $price = '9999999999999999.99';
        $taxRate = '0.9999';

        $product = $this->createProduct(null, [
            'sku' => 'SKU-DEC-ADMIN',
            'price' => $price,
            'tax_rate' => $taxRate,
        ]);

        $response = $this->getJson('/api/admin/products/'.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.price', $price)
            ->assertJsonPath('data.tax_rate', $taxRate);

        $this->assertIsString($response->json('data.price'));
        $this->assertIsString($response->json('data.tax_rate'));
        $this->assertSame($price, $response->json('data.price'));
        $this->assertNotSame((string) (float) $price, $response->json('data.price'));
    }

    public function test_duplicate_sku_returns_validation_error(): void
    {
        $this->actingAsAdmin();
        $product = $this->createProduct();

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'name' => 'Otro',
            'price' => '1.00',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 1,
        ]);

        $this->assertApiError($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('sku', $response->json('details.errors') ?? []);
    }

    public function test_negative_price_and_stock_return_validation_error(): void
    {
        $this->actingAsAdmin();
        $category = $this->createCategory();

        $price = $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'SKU-NEG-P',
            'name' => 'Neg price',
            'price' => -1,
            'currency' => 'COP',
            'tax_rate' => '0.19',
            'stock_quantity' => 1,
        ]);
        $this->assertApiError($price, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('price', $price->json('details.errors') ?? []);

        $stock = $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'sku' => 'SKU-NEG-S',
            'name' => 'Neg stock',
            'price' => '1.00',
            'currency' => 'COP',
            'tax_rate' => '0.19',
            'stock_quantity' => -2,
        ]);
        $this->assertApiError($stock, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('stock_quantity', $stock->json('details.errors') ?? []);
    }

    public function test_missing_category_returns_validation_error(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/products', [
            'category_id' => (string) Str::uuid(),
            'sku' => 'SKU-NO-CAT',
            'name' => 'Sin categoría',
            'price' => '1.00',
            'currency' => 'COP',
            'tax_rate' => '0.19',
            'stock_quantity' => 1,
        ]);

        $this->assertApiError($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('category_id', $response->json('details.errors') ?? []);
    }

    public function test_missing_product_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->assertApiError(
            $this->getJson('/api/admin/products/'.(string) Str::uuid()),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }

    public function test_delete_product_with_cart_item_returns_conflict(): void
    {
        $this->actingAsAdmin();
        $product = $this->createProduct();
        $cart = Cart::query()->create([
            'status' => 'ACTIVE',
            'currency' => 'COP',
        ]);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->deleteJson("/api/admin/products/{$product->id}");

        $this->assertApiError($response, 409, 'RESOURCE_IN_USE');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_delete_product_with_order_item_returns_conflict(): void
    {
        $this->actingAsAdmin();
        $product = $this->createProduct();

        $order = Order::query()->create([
            'order_number' => 'RTR-CRUD-0001',
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => 'Cliente Sintetico',
            'customer_email' => 'order@example.test',
            'customer_phone' => '3001112233',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1001',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '10000.00',
            'tax_total' => '1900.00',
            'total' => '11900.00',
            'idempotency_key' => 'idem-product-delete-1',
            'idempotency_request_hash' => 'hash-product-delete-1',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'tax_rate' => $product->tax_rate,
            'tax_amount' => '1900.00',
            'line_subtotal' => '10000.00',
            'line_total' => '11900.00',
        ]);

        $response = $this->deleteJson("/api/admin/products/{$product->id}");

        $this->assertApiError($response, 409, 'RESOURCE_IN_USE');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_name' => $product->name,
        ]);
    }
}
