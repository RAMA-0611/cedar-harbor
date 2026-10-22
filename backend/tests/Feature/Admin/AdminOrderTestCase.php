<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Checkout\CheckoutTestCase;

abstract class AdminOrderTestCase extends CheckoutTestCase
{
    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin-'.Str::lower(Str::random(6)).'@example.test',
        ]);

        $this->actingAs($admin);

        return $admin;
    }

    /**
     * @param  array<string, mixed>  $checkoutOverrides
     * @return array{user: User, customer: \App\Models\Customer, product: Product, orderId: string, orderNumber: string, stockBefore: int, subtotal: string, tax_total: string, total: string}
     */
    protected function placePendingOrder(array $checkoutOverrides = [], ?string $idempotencyKey = null): array
    {
        $pair = $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, [
            'price' => '10000.00',
            'tax_rate' => '0.1900',
            'stock_quantity' => 10,
        ]);
        $stockBefore = (int) $product->stock_quantity;

        $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $created = $this->postJson(
            '/api/orders',
            $this->checkoutPayload($checkoutOverrides),
            ['Idempotency-Key' => $idempotencyKey ?? 'admin-ord-'.Str::lower(Str::random(12))],
        )->assertCreated();

        return [
            'user' => $pair['user'],
            'customer' => $pair['customer'],
            'product' => $product,
            'orderId' => $created->json('id'),
            'orderNumber' => $created->json('order_number'),
            'stockBefore' => $stockBefore,
            'subtotal' => $created->json('subtotal'),
            'tax_total' => $created->json('tax_total'),
            'total' => $created->json('total'),
        ];
    }
}
