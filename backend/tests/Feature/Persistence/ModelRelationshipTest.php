<?php

namespace Tests\Feature\Persistence;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_category(): void
    {
        $this->seed(CatalogSeeder::class);

        $product = Product::query()->with('category')->first();
        $this->assertNotNull($product);
        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertTrue($product->category->products->contains($product));
    }

    public function test_catalog_seeder_creates_minimum_data(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertGreaterThanOrEqual(4, Category::query()->count());
        $this->assertGreaterThanOrEqual(8, Product::query()->count());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_cart_item_relations(): void
    {
        $this->seed(CatalogSeeder::class);
        $product = Product::query()->firstOrFail();
        $cart = Cart::query()->create(['status' => 'ACTIVE', 'currency' => 'COP']);
        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->assertTrue($item->cart->is($cart));
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($cart->items->contains($item));
    }

    public function test_order_item_snapshot_relation(): void
    {
        $this->seed(CatalogSeeder::class);
        $product = Product::query()->firstOrFail();
        $order = Order::query()->create([
            'order_number' => 'RTR-REL-0001',
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => 'Rel Test',
            'customer_email' => 'rel@example.test',
            'customer_phone' => '300',
            'customer_document_type' => 'CC',
            'customer_document_number' => '99',
            'delivery_mode' => 'COORDINATED_SHIPPING',
            'delivery_address' => 'Calle 10 #20-30',
            'subtotal' => '100.00',
            'tax_total' => '19.00',
            'total' => '119.00',
            'idempotency_key' => 'idem-rel-1',
            'idempotency_request_hash' => 'hash-rel-1',
        ]);

        $line = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'tax_rate' => $product->tax_rate,
            'tax_amount' => '19.00',
            'line_subtotal' => '100.00',
            'line_total' => '119.00',
        ]);

        $this->assertTrue($line->order->is($order));
        $this->assertTrue($line->product->is($product));
        $this->assertTrue($order->items->contains($line));
    }
}
