<?php

namespace Tests\Feature\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Str;

class CartTest extends CartTestCase
{
    public function test_get_cart_creates_empty_active_cart_and_reuses_it(): void
    {
        ['customer' => $customer] = $this->actingAsCustomerWithProfile();

        $first = $this->getJson('/api/cart');
        $first->assertOk()
            ->assertJsonPath('items', [])
            ->assertJsonPath('subtotal', '0.00')
            ->assertJsonPath('tax_total', '0.00')
            ->assertJsonPath('total', '0.00')
            ->assertJsonPath('currency', 'COP');

        $cartId = $first->json('id');
        $this->assertNotEmpty($cartId);
        $this->assertDatabaseHas('carts', [
            'id' => $cartId,
            'customer_id' => $customer->id,
            'status' => 'ACTIVE',
        ]);

        $second = $this->getJson('/api/cart');
        $second->assertOk()->assertJsonPath('id', $cartId);
        $this->assertSame(1, Cart::query()->where('customer_id', $customer->id)->where('status', 'ACTIVE')->count());
    }

    public function test_guest_gets_unauthenticated(): void
    {
        $this->assertApiError($this->getJson('/api/cart'), 401, 'UNAUTHENTICATED');
    }

    public function test_admin_gets_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->assertApiError($this->getJson('/api/cart'), 403, 'FORBIDDEN');
    }

    public function test_customer_without_profile_gets_conflict(): void
    {
        $user = User::factory()->customer()->create();
        $this->actingAs($user);

        $this->assertApiError($this->getJson('/api/cart'), 409, 'CUSTOMER_PROFILE_REQUIRED');
    }

    public function test_add_update_delete_cart_item_lifecycle(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, [
            'sku' => 'CART-LIFE-1',
            'price' => '45000.00',
            'tax_rate' => '0.1900',
            'stock_quantity' => 20,
        ]);

        $add = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => '1.00', // malicious — must be ignored (not in request rules)
        ]);

        $add->assertOk()
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.unit_price', '45000.00')
            ->assertJsonPath('items.0.sku', 'CART-LIFE-1');

        $itemId = $add->json('items.0.id');
        $this->assertDatabaseHas('cart_items', [
            'id' => $itemId,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemId,
            'product_id' => $product->id,
            'quantity' => 2,
            'status' => 'ACTIVE',
        ]);

        $merge = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $merge->assertOk()->assertJsonPath('items.0.quantity', 3);
        $this->assertSame(1, CartItem::query()->where('product_id', $product->id)->count());

        $update = $this->patchJson('/api/cart/items/'.$itemId, ['quantity' => 4]);
        $update->assertOk()->assertJsonPath('items.0.quantity', 4);
        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemId,
            'quantity' => 4,
            'status' => 'ACTIVE',
        ]);

        $delete = $this->deleteJson('/api/cart/items/'.$itemId);
        $delete->assertNoContent();
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => null,
            'product_id' => $product->id,
            'status' => 'RELEASED',
        ]);
        $this->assertNotNull(
            StockReservation::query()->where('product_id', $product->id)->where('status', 'RELEASED')->value('released_at')
        );
    }

    public function test_inactive_product_or_category_returns_not_found(): void
    {
        $this->actingAsCustomerWithProfile();
        $inactiveProduct = $this->createProduct(null, ['is_active' => false]);
        $inactiveCategory = $this->createCategory(['is_active' => false, 'slug' => 'inactive-'.Str::random(4)]);
        $productInInactiveCat = $this->createProduct($inactiveCategory);

        $this->assertApiError(
            $this->postJson('/api/cart/items', ['product_id' => $inactiveProduct->id, 'quantity' => 1]),
            404,
            'RESOURCE_NOT_FOUND',
        );
        $this->assertApiError(
            $this->postJson('/api/cart/items', ['product_id' => $productInInactiveCat->id, 'quantity' => 1]),
            404,
            'RESOURCE_NOT_FOUND',
        );
        $this->assertApiError(
            $this->postJson('/api/cart/items', ['product_id' => (string) Str::uuid(), 'quantity' => 1]),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }

    public function test_other_customer_item_returns_not_found(): void
    {
        $a = $this->actingAsCustomerWithProfile();
        $product = $this->createProduct();
        $add = $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]);
        $itemId = $add->json('items.0.id');

        $b = $this->createCustomerActor();
        $this->actingAs($b['user']);

        $this->assertApiError(
            $this->patchJson('/api/cart/items/'.$itemId, ['quantity' => 2]),
            404,
            'RESOURCE_NOT_FOUND',
        );
        $this->assertApiError(
            $this->deleteJson('/api/cart/items/'.$itemId),
            404,
            'RESOURCE_NOT_FOUND',
        );

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 1]);
        unset($a);
    }
}
