<?php

namespace Tests\Feature\Cart;

use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

class CartReservationTest extends CartTestCase
{
    public function test_insufficient_stock_returns_422_without_mutating(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 2]);

        $ok = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $ok->assertOk();
        $itemId = $ok->json('items.0.id');

        $fail = $this->patchJson('/api/cart/items/'.$itemId, ['quantity' => 3]);
        $this->assertApiError($fail, 422, 'INSUFFICIENT_STOCK');
        $this->assertSame(2, $fail->json('details.available_quantity'));
        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 2]);
    }

    public function test_zero_stock_add_is_insufficient(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 0]);

        $this->assertApiError(
            $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]),
            422,
            'INSUFFICIENT_STOCK',
        );
    }

    public function test_expired_reservation_stops_discounting_and_can_be_renewed(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);

        $add = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $itemId = $add->json('items.0.id');

        StockReservation::query()
            ->where('cart_item_id', $itemId)
            ->where('status', 'ACTIVE')
            ->update(['expires_at' => now()->subMinute()]);

        $get = $this->getJson('/api/cart');
        $get->assertOk();
        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemId,
            'status' => 'EXPIRED',
        ]);

        // After expiry, availability for this line should again allow up to stock.
        $this->assertSame(5, $get->json('items.0.available_quantity'));

        $update = $this->patchJson('/api/cart/items/'.$itemId, ['quantity' => 2]);
        $update->assertOk();
        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemId,
            'status' => 'ACTIVE',
            'quantity' => 2,
        ]);
        $this->assertTrue(
            StockReservation::query()
                ->where('cart_item_id', $itemId)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->exists()
        );
    }

    public function test_get_cart_clears_expires_at_when_all_reservations_expire(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);

        $add = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $cartId = $add->json('id');
        $itemId = $add->json('items.0.id');
        $staleExpiry = now()->subMinutes(10);

        StockReservation::query()
            ->where('cart_item_id', $itemId)
            ->where('status', 'ACTIVE')
            ->update(['expires_at' => $staleExpiry]);

        \App\Models\Cart::query()->whereKey($cartId)->update([
            'expires_at' => $staleExpiry,
        ]);

        $get = $this->getJson('/api/cart');
        $get->assertOk()
            ->assertJsonPath('expires_at', null);

        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemId,
            'status' => 'EXPIRED',
        ]);
        $this->assertDatabaseHas('carts', [
            'id' => $cartId,
            'expires_at' => null,
        ]);
    }

    public function test_get_cart_keeps_max_active_expiry_when_partially_expired(): void
    {
        $this->actingAsCustomerWithProfile();
        $productA = $this->createProduct(null, ['sku' => 'EXP-A', 'stock_quantity' => 5]);
        $productB = $this->createProduct(null, ['sku' => 'EXP-B', 'stock_quantity' => 5]);

        $addA = $this->postJson('/api/cart/items', [
            'product_id' => $productA->id,
            'quantity' => 1,
        ])->assertOk();
        $cartId = $addA->json('id');
        $itemA = $addA->json('items.0.id');

        $addB = $this->postJson('/api/cart/items', [
            'product_id' => $productB->id,
            'quantity' => 1,
        ])->assertOk();
        $itemB = collect($addB->json('items'))->firstWhere('product_id', $productB->id)['id'];

        $past = now()->subMinutes(5);
        $future = now()->addMinutes(25);

        StockReservation::query()
            ->where('cart_item_id', $itemA)
            ->where('status', 'ACTIVE')
            ->update(['expires_at' => $past]);

        StockReservation::query()
            ->where('cart_item_id', $itemB)
            ->where('status', 'ACTIVE')
            ->update(['expires_at' => $future]);

        $expectedActiveExpiry = StockReservation::query()
            ->where('cart_item_id', $itemB)
            ->where('status', 'ACTIVE')
            ->value('expires_at');

        \App\Models\Cart::query()->whereKey($cartId)->update([
            'expires_at' => $past,
        ]);

        $get = $this->getJson('/api/cart');
        $get->assertOk();

        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemA,
            'status' => 'EXPIRED',
        ]);
        $this->assertDatabaseHas('stock_reservations', [
            'cart_item_id' => $itemB,
            'status' => 'ACTIVE',
        ]);

        $responseExpiry = $get->json('expires_at');
        $this->assertNotNull($responseExpiry);

        $dbExpiry = \App\Models\Cart::query()->whereKey($cartId)->value('expires_at');
        $this->assertNotNull($dbExpiry);
        $this->assertTrue($dbExpiry->isFuture());
        $this->assertSame(
            $expectedActiveExpiry->copy()->utc()->toISOString(),
            $dbExpiry->copy()->utc()->toISOString(),
        );
        $this->assertSame(
            $dbExpiry->copy()->utc()->toISOString(),
            \Illuminate\Support\Carbon::parse($responseExpiry)->utc()->toISOString(),
        );
    }

    public function test_available_quantity_excludes_own_reservation(): void
    {
        $product = $this->createProduct(null, ['stock_quantity' => 10]);

        $a = $this->actingAsCustomerWithProfile();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertOk();

        $b = $this->createCustomerActor();
        $this->actingAs($b['user']);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $this->actingAs($a['user']);
        $cartA = $this->getJson('/api/cart');
        $cartA->assertOk()->assertJsonPath('items.0.available_quantity', 8);

        $this->assertApiError(
            $this->patchJson('/api/cart/items/'.$cartA->json('items.0.id'), ['quantity' => 9]),
            422,
            'INSUFFICIENT_STOCK',
        );

        $this->patchJson('/api/cart/items/'.$cartA->json('items.0.id'), ['quantity' => 8])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 8);
    }

    public function test_sequential_competition_for_last_unit(): void
    {
        $product = $this->createProduct(null, ['stock_quantity' => 1]);

        $a = $this->actingAsCustomerWithProfile();
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk();

        $b = $this->createCustomerActor();
        $this->actingAs($b['user']);
        $this->assertApiError(
            $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1]),
            422,
            'INSUFFICIENT_STOCK',
        );

        $activeSum = (int) StockReservation::query()
            ->where('product_id', $product->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->sum('quantity');

        $this->assertLessThanOrEqual(1, $activeSum);
        unset($a);
    }

    public function test_cart_shows_current_catalog_price_not_snapshot(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, [
            'price' => '10000.00',
            'tax_rate' => '0.1900',
            'stock_quantity' => 5,
        ]);

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk()->assertJsonPath('items.0.unit_price', '10000.00');

        DB::table('products')->where('id', $product->id)->update(['price' => '12500.00']);

        $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('items.0.unit_price', '12500.00')
            ->assertJsonPath('subtotal', '12500.00');
    }
}
