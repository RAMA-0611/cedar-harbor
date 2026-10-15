<?php

namespace Tests\Feature\Persistence;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseConstraintTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategoryProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Prueba',
            'slug' => 'prueba-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);

        return Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'name' => 'Producto de prueba',
            'price' => '10.50',
            'currency' => 'COP',
            'tax_rate' => '0.1900',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_duplicate_sku_fails(): void
    {
        $product = $this->seedCategoryProduct();

        $this->expectException(QueryException::class);

        Product::query()->create([
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'name' => 'Duplicado',
            'price' => '1.00',
            'currency' => 'COP',
            'tax_rate' => '0.0000',
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
    }

    public function test_cart_item_unique_cart_product(): void
    {
        $product = $this->seedCategoryProduct();
        $cart = Cart::query()->create([
            'status' => 'ACTIVE',
            'currency' => 'COP',
        ]);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->expectException(QueryException::class);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_invalid_product_fk_fails(): void
    {
        $cart = Cart::query()->create([
            'status' => 'ACTIVE',
            'currency' => 'COP',
        ]);

        $this->expectException(QueryException::class);

        CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => (string) Str::uuid(),
            'quantity' => 1,
        ]);
    }

    public function test_duplicate_idempotency_key_fails(): void
    {
        $payload = [
            'order_number' => 'RTR-TEST-0001',
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => 'A',
            'customer_email' => 'a@example.test',
            'customer_phone' => '300',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '10.00',
            'tax_total' => '1.90',
            'total' => '11.90',
            'idempotency_key' => 'idem-1',
            'idempotency_request_hash' => 'hash-1',
        ];

        Order::query()->create($payload);

        $this->expectException(QueryException::class);

        $payload['order_number'] = 'RTR-TEST-0002';
        $payload['idempotency_request_hash'] = 'hash-2';
        Order::query()->create($payload);
    }

    public function test_webhook_duplicate_provider_event_fails(): void
    {
        WebhookEvent::query()->create([
            'provider' => 'WOMPI',
            'external_event_id' => 'evt-1',
            'processing_status' => 'RECEIVED',
            'signature_verified' => false,
            'received_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        WebhookEvent::query()->create([
            'provider' => 'WOMPI',
            'external_event_id' => 'evt-1',
            'processing_status' => 'RECEIVED',
            'signature_verified' => false,
            'received_at' => now(),
        ]);
    }

    public function test_delivery_check_pickup_requires_null_address(): void
    {
        $this->expectException(QueryException::class);

        Order::query()->create([
            'order_number' => 'RTR-TEST-ADDR-1',
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => 'A',
            'customer_email' => 'a@example.test',
            'customer_phone' => '300',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => 'Calle 1',
            'subtotal' => '10.00',
            'tax_total' => '1.90',
            'total' => '11.90',
            'idempotency_key' => 'idem-addr-1',
            'idempotency_request_hash' => 'hash-addr-1',
        ]);
    }

    public function test_delivery_check_shipping_requires_address(): void
    {
        $this->expectException(QueryException::class);

        Order::query()->create([
            'order_number' => 'RTR-TEST-ADDR-2',
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => 'A',
            'customer_email' => 'a@example.test',
            'customer_phone' => '300',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1',
            'delivery_mode' => 'COORDINATED_SHIPPING',
            'delivery_address' => null,
            'subtotal' => '10.00',
            'tax_total' => '1.90',
            'total' => '11.90',
            'idempotency_key' => 'idem-addr-2',
            'idempotency_request_hash' => 'hash-addr-2',
        ]);
    }

    public function test_money_preserves_decimal_precision(): void
    {
        $product = $this->seedCategoryProduct();
        $product->price = '19.99';
        $product->save();
        $product->refresh();

        $this->assertSame('19.99', (string) $product->price);
    }

    public function test_payments_allow_multiple_null_provider_references(): void
    {
        $order = Order::query()->create([
            'order_number' => 'RTR-TEST-PAY-1',
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => 'A',
            'customer_email' => 'a@example.test',
            'customer_phone' => '300',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '10.00',
            'tax_total' => '1.90',
            'total' => '11.90',
            'idempotency_key' => 'idem-pay-1',
            'idempotency_request_hash' => 'hash-pay-1',
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'WOMPI',
            'provider_reference' => null,
            'status' => 'PENDING',
            'amount' => '11.90',
            'currency' => 'COP',
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'WOMPI',
            'provider_reference' => null,
            'status' => 'PENDING',
            'amount' => '11.90',
            'currency' => 'COP',
        ]);

        $this->assertSame(2, Payment::query()->where('order_id', $order->id)->count());
    }
}
