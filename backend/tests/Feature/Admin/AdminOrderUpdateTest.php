<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockReservation;

class AdminOrderUpdateTest extends AdminOrderTestCase
{
    public function test_admin_updates_allowed_snapshot_fields(): void
    {
        $placed = $this->placePendingOrder([], 'admin-patch-fields-01');
        $this->actingAsAdmin();

        $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
            'customer_name' => 'Nombre Admin Editado',
            'customer_email' => 'admin.editado@example.test',
            'customer_phone' => '+57-300-222-3333',
            'customer_document_type' => 'NIT',
            'customer_document_number' => '900111222',
        ])
            ->assertOk()
            ->assertJsonPath('customer.name', 'Nombre Admin Editado')
            ->assertJsonPath('customer.email', 'admin.editado@example.test')
            ->assertJsonPath('customer.phone', '+57-300-222-3333')
            ->assertJsonPath('customer.document_type', 'NIT')
            ->assertJsonPath('customer.document_number', '900111222')
            ->assertJsonPath('subtotal', $placed['subtotal'])
            ->assertJsonPath('total', $placed['total']);
    }

    public function test_pickup_normalizes_address_to_null(): void
    {
        $placed = $this->placePendingOrder([
            'delivery' => [
                'mode' => 'COORDINATED_SHIPPING',
                'address' => 'Calle Envio Original 99',
            ],
        ], 'admin-patch-pickup-01');

        $this->actingAsAdmin();
        $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
            'delivery_mode' => 'PICKUP',
        ])
            ->assertOk()
            ->assertJsonPath('delivery.mode', 'PICKUP')
            ->assertJsonPath('delivery.address', null);

        $this->assertNull(Order::query()->find($placed['orderId'])?->delivery_address);
    }

    public function test_coordinated_shipping_requires_address(): void
    {
        $placed = $this->placePendingOrder([], 'admin-patch-ship-01');
        $this->actingAsAdmin();

        $this->assertApiError(
            $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
                'delivery_mode' => 'COORDINATED_SHIPPING',
            ]),
            422,
            'VALIDATION_ERROR',
        );

        $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
            'delivery_mode' => 'COORDINATED_SHIPPING',
            'delivery_address' => 'Calle Nueva Coordinada 45',
        ])
            ->assertOk()
            ->assertJsonPath('delivery.mode', 'COORDINATED_SHIPPING')
            ->assertJsonPath('delivery.address', 'Calle Nueva Coordinada 45');
    }

    public function test_unknown_and_protected_fields_are_rejected(): void
    {
        $placed = $this->placePendingOrder([], 'admin-patch-forbid-01');
        $this->actingAsAdmin();
        $url = '/api/admin/orders/'.$placed['orderId'];

        foreach ([
            ['extra' => 'nope'],
            ['subtotal' => '1.00'],
            ['tax_total' => '1.00'],
            ['total' => '1.00'],
            ['order_number' => 'RTR-HACKED'],
            ['customer_id' => $placed['customer']->id],
            ['idempotency_key' => 'x'],
            ['idempotency_request_hash' => str_repeat('b', 64)],
        ] as $payload) {
            $this->assertApiError($this->patchJson($url, $payload), 422, 'VALIDATION_ERROR');
        }

        $order = Order::query()->findOrFail($placed['orderId']);
        $this->assertSame($placed['orderNumber'], $order->order_number);
        $this->assertSame($placed['subtotal'], (string) $order->subtotal);
        $this->assertSame($placed['customer']->id, $order->customer_id);
    }

    public function test_admin_cancels_pending_payment_and_releases_reservations(): void
    {
        $placed = $this->placePendingOrder([], 'admin-cancel-01');
        $itemBefore = OrderItem::query()->where('order_id', $placed['orderId'])->get()->toArray();

        $this->actingAsAdmin();
        $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
            'status' => 'CANCELLED',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED');

        $order = Order::query()->findOrFail($placed['orderId']);
        $this->assertSame('CANCELLED', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame($placed['subtotal'], (string) $order->subtotal);
        $this->assertSame($placed['tax_total'], (string) $order->tax_total);
        $this->assertSame($placed['total'], (string) $order->total);

        $reservation = StockReservation::query()->where('order_id', $placed['orderId'])->firstOrFail();
        $this->assertSame('RELEASED', $reservation->status);
        $this->assertNotNull($reservation->released_at);
        $this->assertSame($placed['stockBefore'], (int) $placed['product']->fresh()->stock_quantity);
        $this->assertEquals(
            $itemBefore,
            OrderItem::query()->where('order_id', $placed['orderId'])->get()->toArray(),
        );

        $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
            'status' => 'CANCELLED',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED');
    }

    public function test_admin_cannot_set_paid_manually(): void
    {
        $placed = $this->placePendingOrder([], 'admin-no-paid-01');
        $this->actingAsAdmin();

        $this->assertApiError(
            $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
                'status' => 'PAID',
            ]),
            422,
            'VALIDATION_ERROR',
        );
        $this->assertSame('PENDING_PAYMENT', Order::query()->find($placed['orderId'])?->status);
    }
}
