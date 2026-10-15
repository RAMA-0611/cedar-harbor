<?php

namespace Tests\Feature\Admin;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Str;

class CustomerCrudTest extends AdminTestCase
{
    public function test_customer_crud_lifecycle(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/admin/customers', [
            'name' => 'Cliente Sintetico A',
            'email' => 'cliente.a@example.test',
            'phone' => '+57-300-111-0001',
            'document_type' => 'CC',
            'document_number' => '2000000001',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.email', 'cliente.a@example.test');

        $id = $create->json('data.id');
        $this->assertDatabaseHas('customers', ['id' => $id]);

        $this->getJson('/api/admin/customers')
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->getJson("/api/admin/customers/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->patchJson("/api/admin/customers/{$id}", [
            'phone' => '+57-300-111-9999',
        ])
            ->assertOk()
            ->assertJsonPath('data.phone', '+57-300-111-9999');

        $this->deleteJson("/api/admin/customers/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('customers', ['id' => $id]);
    }

    public function test_duplicate_user_id_returns_validation_error(): void
    {
        $this->actingAsAdmin();
        $user = User::factory()->customer()->create();

        Customer::query()->create([
            'user_id' => $user->id,
            'name' => 'Primero',
            'email' => 'primero@example.test',
            'phone' => '300',
            'document_type' => 'CC',
            'document_number' => '1',
        ]);

        $response = $this->postJson('/api/admin/customers', [
            'user_id' => $user->id,
            'name' => 'Segundo',
            'email' => 'segundo@example.test',
            'phone' => '301',
            'document_type' => 'CC',
            'document_number' => '2',
        ]);

        $this->assertApiError($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey('user_id', $response->json('details.errors') ?? []);
    }

    public function test_delete_customer_nullifies_cart_and_order_without_deleting_them(): void
    {
        $this->actingAsAdmin();

        $customer = Customer::query()->create([
            'name' => 'Cliente Integridad',
            'email' => 'integrity@example.test',
            'phone' => '3002223344',
            'document_type' => 'CC',
            'document_number' => '300300300',
        ]);

        $cart = Cart::query()->create([
            'customer_id' => $customer->id,
            'status' => 'ACTIVE',
            'currency' => 'COP',
        ]);

        $order = Order::query()->create([
            'order_number' => 'RTR-CUST-0001',
            'customer_id' => $customer->id,
            'status' => 'DRAFT',
            'currency' => 'COP',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'customer_document_type' => $customer->document_type,
            'customer_document_number' => $customer->document_number,
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '10.00',
            'tax_total' => '1.90',
            'total' => '11.90',
            'idempotency_key' => 'idem-customer-delete-1',
            'idempotency_request_hash' => 'hash-customer-delete-1',
        ]);

        $this->deleteJson("/api/admin/customers/{$customer->id}")->assertNoContent();

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('carts', ['id' => $cart->id, 'customer_id' => null]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_id' => null,
            'customer_name' => 'Cliente Integridad',
            'customer_email' => 'integrity@example.test',
        ]);
    }

    public function test_missing_customer_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->assertApiError(
            $this->getJson('/api/admin/customers/'.(string) Str::uuid()),
            404,
            'RESOURCE_NOT_FOUND',
        );
    }
}
