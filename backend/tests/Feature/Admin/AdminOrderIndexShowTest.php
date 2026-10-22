<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AdminOrderIndexShowTest extends AdminOrderTestCase
{
    public function test_admin_lists_orders_newest_first(): void
    {
        $older = $this->placePendingOrder([], 'admin-list-old-01');
        $newer = $this->placePendingOrder([], 'admin-list-new-01');

        Order::query()->whereKey($older['orderId'])->update([
            'created_at' => Carbon::now()->subMinutes(5),
        ]);
        Order::query()->whereKey($newer['orderId'])->update([
            'created_at' => Carbon::now()->subMinute(),
        ]);

        $this->actingAsAdmin();
        $response = $this->getJson('/api/admin/orders')->assertOk();

        $this->assertSame(1, $response->json('meta.page'));
        $this->assertSame(20, $response->json('meta.per_page'));
        $this->assertGreaterThanOrEqual(2, $response->json('meta.total'));
        $this->assertSame($newer['orderId'], $response->json('data.0.id'));
        $ids = $response->json('data.*.id');
        $this->assertContains($older['orderId'], $ids);
        $this->assertArrayNotHasKey('idempotency_key', $response->json('data.0'));
        $this->assertArrayNotHasKey('idempotency_request_hash', $response->json('data.0'));
    }

    public function test_index_filters_status_and_search(): void
    {
        $keep = $this->placePendingOrder([
            'customer' => [
                'name' => 'Zeta Searchable Unique',
                'email' => 'zeta.search@example.test',
            ],
        ], 'admin-search-keep-01');
        $cancel = $this->placePendingOrder([], 'admin-search-cancel-01');

        $this->actingAs($cancel['user']);
        $this->postJson('/api/orders/'.$cancel['orderId'].'/cancel')->assertOk();

        $this->actingAsAdmin();

        $byStatus = $this->getJson('/api/admin/orders?status=CANCELLED')->assertOk();
        $this->assertSame([$cancel['orderId']], $byStatus->json('data.*.id'));

        $bySearch = $this->getJson('/api/admin/orders?search=Zeta%20Searchable')->assertOk();
        $this->assertSame([$keep['orderId']], $bySearch->json('data.*.id'));

        $byNumber = $this->getJson('/api/admin/orders?search='.urlencode($keep['orderNumber']))->assertOk();
        $this->assertSame([$keep['orderId']], $byNumber->json('data.*.id'));
    }

    public function test_index_pagination_is_stable(): void
    {
        $this->placePendingOrder([], 'admin-page-a-01');
        $this->placePendingOrder([], 'admin-page-b-01');

        $this->actingAsAdmin();
        $page1 = $this->getJson('/api/admin/orders?page=1&per_page=1')->assertOk();
        $this->assertSame(1, $page1->json('meta.page'));
        $this->assertSame(1, $page1->json('meta.per_page'));
        $this->assertGreaterThanOrEqual(2, $page1->json('meta.total'));
        $this->assertCount(1, $page1->json('data'));

        $page2 = $this->getJson('/api/admin/orders?page=2&per_page=1')->assertOk();
        $this->assertNotSame($page1->json('data.0.id'), $page2->json('data.0.id'));
    }

    public function test_index_guest_and_customer_are_rejected(): void
    {
        $this->assertApiError($this->getJson('/api/admin/orders'), 401, 'UNAUTHENTICATED');

        $this->actingAsCustomerWithProfile();
        $this->assertApiError($this->getJson('/api/admin/orders'), 403, 'FORBIDDEN');
    }

    public function test_admin_can_show_any_customer_order(): void
    {
        $placed = $this->placePendingOrder([
            'delivery' => [
                'mode' => 'COORDINATED_SHIPPING',
                'address' => 'Calle Admin Show 10',
            ],
        ], 'admin-show-any-01');

        $this->actingAsAdmin();
        $this->getJson('/api/admin/orders/'.$placed['orderId'])
            ->assertOk()
            ->assertJsonPath('id', $placed['orderId'])
            ->assertJsonPath('status', 'PENDING_PAYMENT')
            ->assertJsonPath('customer.name', 'Cliente Demo Rotor')
            ->assertJsonPath('delivery.mode', 'COORDINATED_SHIPPING')
            ->assertJsonPath('items.0.quantity', 1)
            ->assertJsonMissingPath('idempotency_key')
            ->assertJsonMissingPath('idempotency_request_hash');
    }

    public function test_show_rejects_invalid_and_missing_ids(): void
    {
        $this->actingAsAdmin();
        $this->assertApiError($this->getJson('/api/admin/orders/not-a-uuid'), 400, 'MALFORMED_REQUEST');
        $this->assertApiError(
            $this->getJson('/api/admin/orders/'.Str::uuid()),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }

    public function test_show_guest_and_customer_are_rejected(): void
    {
        $placed = $this->placePendingOrder([], 'admin-show-auth-01');

        $this->app['auth']->forgetGuards();
        $this->assertApiError($this->getJson('/api/admin/orders/'.$placed['orderId']), 401, 'UNAUTHENTICATED');

        $this->actingAsCustomerWithProfile();
        $this->assertApiError($this->getJson('/api/admin/orders/'.$placed['orderId']), 403, 'FORBIDDEN');
    }
}
