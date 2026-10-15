<?php

namespace Tests\Feature\Orders;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Feature\Checkout\CheckoutTestCase;

class CreateOrderTest extends CheckoutTestCase
{
    public function test_happy_path_creates_pending_payment_order(): void
    {
        ['customer' => $customer] = $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, [
            'sku' => 'ORD-HAPPY-1',
            'name' => 'Producto Orden',
            'price' => '10000.00',
            'tax_rate' => '0.1900',
            'stock_quantity' => 10,
        ]);
        $stockBefore = (int) $product->stock_quantity;

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])->assertOk();

        $response = $this->postJson('/api/orders', $this->checkoutPayload([
            'delivery' => [
                'mode' => 'COORDINATED_SHIPPING',
                'address' => 'Calle Demo 12 # 34-56',
            ],
        ]), ['Idempotency-Key' => 'order-happy-key-01']);

        $response->assertCreated()
            ->assertJsonPath('status', 'PENDING_PAYMENT')
            ->assertJsonPath('customer.name', 'Cliente Demo Rotor')
            ->assertJsonPath('delivery.mode', 'COORDINATED_SHIPPING')
            ->assertJsonPath('items.0.sku', 'ORD-HAPPY-1')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('subtotal', '20000.00')
            ->assertJsonPath('tax_total', '3800.00')
            ->assertJsonPath('total', '23800.00');

        $orderId = $response->json('id');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('carts', [
            'customer_id' => $customer->id,
            'status' => 'CONVERTED',
        ]);
        $this->assertDatabaseHas('stock_reservations', [
            'order_id' => $orderId,
            'status' => 'ACTIVE',
        ]);
        $this->assertSame($stockBefore, (int) $product->fresh()->stock_quantity);
        $this->assertMatchesRegularExpression('/^RTR-\d{8}-[A-F0-9]{8}$/', $response->json('order_number'));
    }

    public function test_snapshot_survives_product_catalog_changes(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, [
            'sku' => 'SNAP-SKU',
            'name' => 'Nombre Original',
            'price' => '10000.00',
            'tax_rate' => '0.1900',
            'stock_quantity' => 5,
        ]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $created = $this->postJson('/api/orders', $this->checkoutPayload(), [
            'Idempotency-Key' => 'order-snap-key-001',
        ])->assertCreated();

        $product->update([
            'name' => 'Nombre Nuevo',
            'price' => '99999.00',
            'tax_rate' => '0.0500',
            'sku' => 'SNAP-SKU-CHANGED',
        ]);

        $this->getJson('/api/orders/'.$created->json('id'))
            ->assertOk()
            ->assertJsonPath('items.0.product_name', 'Nombre Original')
            ->assertJsonPath('items.0.sku', 'SNAP-SKU')
            ->assertJsonPath('items.0.unit_price', '10000.00');

        $item = OrderItem::query()->where('order_id', $created->json('id'))->firstOrFail();
        $this->assertSame('0.1900', (string) $item->tax_rate);
    }

    public function test_price_change_before_order_uses_current_server_price(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, [
            'price' => '10000.00',
            'tax_rate' => '0.0000',
            'stock_quantity' => 5,
        ]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $product->update(['price' => '15000.00']);

        $this->postJson('/api/orders', $this->checkoutPayload(), [
            'Idempotency-Key' => 'order-price-now-01',
        ])
            ->assertCreated()
            ->assertJsonPath('items.0.unit_price', '15000.00')
            ->assertJsonPath('subtotal', '15000.00');
    }

    public function test_idempotent_replay_same_key_same_payload(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $payload = $this->checkoutPayload();
        $headers = ['Idempotency-Key' => 'order-replay-same-1'];

        $first = $this->postJson('/api/orders', $payload, $headers)->assertCreated();
        $second = $this->postJson('/api/orders', $payload, $headers)->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, OrderItem::query()->count());
    }

    public function test_idempotent_conflict_on_payload_mismatch(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $key = 'order-conflict-pay-1';
        $this->postJson('/api/orders', $this->checkoutPayload(), ['Idempotency-Key' => $key])->assertCreated();

        $this->assertApiError(
            $this->postJson('/api/orders', $this->checkoutPayload([
                'delivery' => [
                    'mode' => 'COORDINATED_SHIPPING',
                    'address' => 'Otra direccion distinta 99',
                ],
            ]), ['Idempotency-Key' => $key]),
            409,
            'IDEMPOTENCY_CONFLICT',
        );
    }

    public function test_idempotent_conflict_across_customers(): void
    {
        $product = $this->createProduct(null, ['stock_quantity' => 10]);
        $key = 'order-cross-cust-01';

        ['user' => $userA] = $this->createCustomerActor();
        $this->actingAs($userA);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $orderA = $this->postJson('/api/orders', $this->checkoutPayload(), ['Idempotency-Key' => $key])->assertCreated();

        ['user' => $userB] = $this->createCustomerActor();
        $this->actingAs($userB);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $conflict = $this->postJson('/api/orders', $this->checkoutPayload(), ['Idempotency-Key' => $key]);
        $this->assertApiError($conflict, 409, 'IDEMPOTENCY_CONFLICT');
        $this->assertStringNotContainsString($orderA->json('id'), $conflict->getContent());
    }

    public function test_replay_after_cart_converted(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $payload = $this->checkoutPayload();
        $headers = ['Idempotency-Key' => 'order-after-convert'];
        $first = $this->postJson('/api/orders', $payload, $headers)->assertCreated();

        $this->assertSame(0, Cart::query()->where('status', 'ACTIVE')->count());

        $replay = $this->postJson('/api/orders', $payload, $headers)->assertCreated();
        $this->assertSame($first->json('id'), $replay->json('id'));
    }

    public function test_cart_empty_on_create_is_422(): void
    {
        $this->actingAsCustomerWithProfile();

        $this->assertApiError(
            $this->postJson('/api/orders', $this->checkoutPayload(), [
                'Idempotency-Key' => 'order-empty-cart-1',
            ]),
            422,
            'CART_EMPTY',
        );
    }

    public function test_expired_reservation_on_create_is_422(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        StockReservation::query()->update(['expires_at' => Carbon::now()->subMinute()]);

        $this->assertApiError(
            $this->postJson('/api/orders', $this->checkoutPayload(), [
                'Idempotency-Key' => 'order-expired-res-1',
            ]),
            422,
            'RESERVATION_EXPIRED',
        );
    }

    public function test_missing_idempotency_key_is_400(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 2]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $this->assertApiError(
            $this->postJson('/api/orders', $this->checkoutPayload()),
            400,
            'MALFORMED_REQUEST',
        );
    }

    public function test_get_status_cancel_and_ownership(): void
    {
        ['user' => $owner] = $this->createCustomerActor();
        $this->actingAs($owner);
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $stockBefore = (int) $product->fresh()->stock_quantity;

        $created = $this->postJson('/api/orders', $this->checkoutPayload(), [
            'Idempotency-Key' => 'order-own-cancel-01',
        ])->assertCreated();
        $orderId = $created->json('id');

        $this->getJson('/api/orders/'.$orderId)->assertOk()->assertJsonPath('id', $orderId);
        $this->getJson('/api/orders/'.$orderId.'/status')
            ->assertOk()
            ->assertJsonPath('order_id', $orderId)
            ->assertJsonPath('status', 'PENDING_PAYMENT')
            ->assertJsonStructure(['order_id', 'status', 'updated_at']);

        $this->assertApiError($this->getJson('/api/orders/not-a-uuid'), 400, 'MALFORMED_REQUEST');

        ['user' => $other] = $this->createCustomerActor();
        $this->actingAs($other);
        $this->assertApiError($this->getJson('/api/orders/'.$orderId), 404, 'RESOURCE_NOT_FOUND');
        $this->assertApiError($this->postJson('/api/orders/'.$orderId.'/cancel'), 404, 'RESOURCE_NOT_FOUND');

        $this->actingAs($owner);
        $this->postJson('/api/orders/'.$orderId.'/cancel')
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED');

        $this->assertNotNull(Order::query()->find($orderId)?->cancelled_at);
        $this->assertDatabaseHas('stock_reservations', [
            'order_id' => $orderId,
            'status' => 'RELEASED',
        ]);
        $this->assertNotNull(StockReservation::query()->where('order_id', $orderId)->value('released_at'));
        $this->assertSame($stockBefore, (int) $product->fresh()->stock_quantity);

        $this->assertApiError(
            $this->postJson('/api/orders/'.$orderId.'/cancel'),
            409,
            'INVALID_ORDER_STATE',
        );
    }

    public function test_cancel_paid_order_conflicts(): void
    {
        ['customer' => $customer, 'user' => $user] = $this->actingAsCustomerWithProfile();
        $order = Order::query()->create([
            'order_number' => 'RTR-'.now()->format('Ymd').'-PAIDTEST',
            'customer_id' => $customer->id,
            'status' => 'PAID',
            'currency' => 'COP',
            'customer_name' => 'X',
            'customer_email' => 'x@example.test',
            'customer_phone' => '+57000',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '1.00',
            'tax_total' => '0.00',
            'total' => '1.00',
            'idempotency_key' => 'paid-order-key-01',
            'idempotency_request_hash' => str_repeat('a', 64),
            'paid_at' => now(),
        ]);

        $this->assertApiError(
            $this->postJson('/api/orders/'.$order->id.'/cancel'),
            409,
            'INVALID_ORDER_STATE',
        );
    }

    public function test_guest_and_admin_forbidden_on_orders(): void
    {
        $this->assertApiError(
            $this->postJson('/api/orders', $this->checkoutPayload(), ['Idempotency-Key' => 'guest-order-k']),
            401,
            'UNAUTHENTICATED',
        );

        $this->actingAs(User::factory()->admin()->create());
        $this->assertApiError(
            $this->getJson('/api/orders/'.(string) Str::uuid()),
            403,
            'FORBIDDEN',
        );
    }

    public function test_rollback_keeps_cart_and_reservations_intact(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 5]);
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();

        $cart = Cart::query()->where('status', 'ACTIVE')->firstOrFail();
        $reservation = StockReservation::query()->where('cart_id', $cart->id)->where('status', 'ACTIVE')->firstOrFail();

        OrderItem::creating(function () {
            throw new \RuntimeException('forced order item failure');
        });

        try {
            $this->assertApiError(
                $this->postJson('/api/orders', $this->checkoutPayload(), [
                    'Idempotency-Key' => 'order-rollback-001',
                ]),
                500,
                'INTERNAL_ERROR',
            );

            $this->assertSame(0, Order::query()->count());
            $this->assertSame(0, OrderItem::query()->count());
            $this->assertDatabaseHas('carts', ['id' => $cart->id, 'status' => 'ACTIVE']);
            $this->assertDatabaseHas('stock_reservations', [
                'id' => $reservation->id,
                'status' => 'ACTIVE',
                'order_id' => null,
            ]);
        } finally {
            OrderItem::flushEventListeners();
            OrderItem::clearBootedModels();
        }
    }

    public function test_order_numbers_are_unique_across_orders(): void
    {
        $this->actingAsCustomerWithProfile();
        $product = $this->createProduct(null, ['stock_quantity' => 20]);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
            $resp = $this->postJson('/api/orders', $this->checkoutPayload(), [
                'Idempotency-Key' => 'order-num-unique-'.$i.Str::random(4),
            ])->assertCreated();
            $numbers[] = $resp->json('order_number');
        }

        $this->assertCount(3, array_unique($numbers));
        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^RTR-\d{8}-[A-F0-9]{8}$/', $number);
        }
    }
}
