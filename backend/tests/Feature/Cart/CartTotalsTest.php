<?php

namespace Tests\Feature\Cart;

class CartTotalsTest extends CartTestCase
{
    public function test_totals_use_half_up_rounding_as_decimal_strings(): void
    {
        $this->actingAsCustomerWithProfile();

        // 333.33 * 3 = 999.99 subtotal; tax 0.1900 → 189.9981 → 190.00 HALF_UP
        $product = $this->createProduct(null, [
            'price' => '333.33',
            'tax_rate' => '0.1900',
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('items.0.unit_price', '333.33')
            ->assertJsonPath('items.0.tax_amount', '190.00')
            ->assertJsonPath('items.0.line_total', '1189.99')
            ->assertJsonPath('subtotal', '999.99')
            ->assertJsonPath('tax_total', '190.00')
            ->assertJsonPath('total', '1189.99');

        $this->assertIsString($response->json('subtotal'));
        $this->assertIsString($response->json('tax_total'));
        $this->assertIsString($response->json('total'));
        $this->assertIsString($response->json('items.0.tax_amount'));
    }

    public function test_update_refreshes_reservation_expiry(): void
    {
        config(['commerce.cart_reservation_minutes' => 30]);
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);

        $add = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $before = $add->json('items.0.reservation_expires_at');
        $this->assertNotEmpty($before);

        $this->travel(5)->minutes();

        $update = $this->patchJson('/api/cart/items/'.$add->json('items.0.id'), [
            'quantity' => 2,
        ])->assertOk();

        $after = $update->json('items.0.reservation_expires_at');
        $this->assertNotEmpty($after);
        $this->assertTrue(strcmp($after, $before) > 0);
        $this->assertSame($after, $update->json('expires_at'));
    }
}
