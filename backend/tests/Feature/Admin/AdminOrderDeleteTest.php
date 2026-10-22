<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Illuminate\Support\Str;

class AdminOrderDeleteTest extends AdminOrderTestCase
{
    public function test_admin_deletes_cancelled_order_without_payments(): void
    {
        $placed = $this->placePendingOrder([], 'admin-del-ok-01');
        $this->actingAsAdmin();

        $this->patchJson('/api/admin/orders/'.$placed['orderId'], [
            'status' => 'CANCELLED',
        ])->assertOk();

        $this->deleteJson('/api/admin/orders/'.$placed['orderId'])->assertNoContent();

        $this->assertDatabaseMissing('orders', ['id' => $placed['orderId']]);
        $this->assertSame(0, OrderItem::query()->where('order_id', $placed['orderId'])->count());
        $this->assertSame(0, StockReservation::query()->where('order_id', $placed['orderId'])->count());
        $this->assertSame(
            0,
            StockReservation::query()->where('product_id', $placed['product']->id)->where('status', 'ACTIVE')->count(),
        );
        $this->assertSame($placed['stockBefore'], (int) $placed['product']->fresh()->stock_quantity);
    }

    public function test_delete_pending_payment_conflicts(): void
    {
        $placed = $this->placePendingOrder([], 'admin-del-pending-01');
        $this->actingAsAdmin();

        $this->assertApiError(
            $this->deleteJson('/api/admin/orders/'.$placed['orderId']),
            409,
            'INVALID_ORDER_STATE',
        );
        $this->assertNotNull(Order::query()->find($placed['orderId']));
    }

    public function test_delete_with_payment_conflicts(): void
    {
        $placed = $this->placePendingOrder([], 'admin-del-pay-01');
        $this->actingAsAdmin();
        $this->patchJson('/api/admin/orders/'.$placed['orderId'], ['status' => 'CANCELLED'])->assertOk();

        Payment::query()->create([
            'order_id' => $placed['orderId'],
            'provider' => 'WOMPI',
            'status' => 'PENDING',
            'amount' => $placed['total'],
            'currency' => 'COP',
        ]);

        $this->assertApiError(
            $this->deleteJson('/api/admin/orders/'.$placed['orderId']),
            409,
            'RESOURCE_IN_USE',
        );
        $this->assertNotNull(Order::query()->find($placed['orderId']));
    }

    public function test_delete_with_webhook_event_conflicts(): void
    {
        $placed = $this->placePendingOrder([], 'admin-del-wh-01');
        $this->actingAsAdmin();
        $this->patchJson('/api/admin/orders/'.$placed['orderId'], ['status' => 'CANCELLED'])->assertOk();

        WebhookEvent::query()->create([
            'provider' => 'WOMPI',
            'external_event_id' => 'evt-'.Str::lower(Str::random(8)),
            'processing_status' => 'RECEIVED',
            'order_id' => $placed['orderId'],
        ]);

        $this->assertApiError(
            $this->deleteJson('/api/admin/orders/'.$placed['orderId']),
            409,
            'RESOURCE_IN_USE',
        );
        $this->assertNotNull(Order::query()->find($placed['orderId']));
    }

    public function test_delete_auth_and_missing(): void
    {
        $placed = $this->placePendingOrder([], 'admin-del-auth-01');

        $this->app['auth']->forgetGuards();
        $this->assertApiError($this->deleteJson('/api/admin/orders/'.$placed['orderId']), 401, 'UNAUTHENTICATED');

        $this->actingAsCustomerWithProfile();
        $this->assertApiError($this->deleteJson('/api/admin/orders/'.$placed['orderId']), 403, 'FORBIDDEN');

        $this->actingAsAdmin();
        $this->assertApiError(
            $this->deleteJson('/api/admin/orders/'.Str::uuid()),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }
}
