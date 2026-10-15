<?php

namespace Tests\Feature\Orders;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderIdempotencyService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderIdempotencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderIdempotencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderIdempotencyService::class);
    }

    public function test_same_key_same_customer_same_hash_returns_same_order(): void
    {
        $customer = $this->makeCustomer();
        $payload = $this->checkoutPayload();
        $key = 'idem-key-replay-001';
        $hash = $this->service->fingerprint($customer->id, $payload);

        $order = $this->createOrder($customer, $key, $hash);

        $replay = $this->service->findReplay($customer, $key, $hash);

        $this->assertNotNull($replay);
        $this->assertSame($order->id, $replay->id);
        $this->assertTrue($replay->relationLoaded('items'));
    }

    public function test_same_key_same_customer_different_payload_conflicts(): void
    {
        $customer = $this->makeCustomer();
        $key = 'idem-key-conflict-01';
        $original = $this->checkoutPayload();
        $hash = $this->service->fingerprint($customer->id, $original);
        $this->createOrder($customer, $key, $hash);

        $altered = $this->checkoutPayload([
            'customer' => ['email' => 'other@example.test'],
        ]);
        $otherHash = $this->service->fingerprint($customer->id, $altered);

        try {
            $this->service->findReplay($customer, $key, $otherHash);
            $this->fail('Expected IDEMPOTENCY_CONFLICT');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('IDEMPOTENCY_CONFLICT', $e->errorCode);
            $this->assertSame('payload_mismatch', $e->details['reason'] ?? null);
            $this->assertArrayNotHasKey('idempotency_request_hash', $e->details);
            $this->assertArrayNotHasKey('hash', $e->details);
        }
    }

    public function test_same_key_other_customer_conflicts_without_leaking_order(): void
    {
        $owner = $this->makeCustomer(['email' => 'owner@example.test']);
        $intruder = $this->makeCustomer(['email' => 'intruder@example.test']);
        $payload = $this->checkoutPayload();
        $key = 'idem-key-cross-cust';
        $hash = $this->service->fingerprint($owner->id, $payload);
        $owned = $this->createOrder($owner, $key, $hash);

        $intruderHash = $this->service->fingerprint($intruder->id, $payload);

        try {
            $this->service->findReplay($intruder, $key, $intruderHash);
            $this->fail('Expected IDEMPOTENCY_CONFLICT');
        } catch (ApiException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('IDEMPOTENCY_CONFLICT', $e->errorCode);
            $this->assertSame('customer_mismatch', $e->details['reason'] ?? null);
            $this->assertStringNotContainsString($owned->id, json_encode($e->details));
            $this->assertStringNotContainsString($owned->order_number, json_encode($e->details));
        }
    }

    public function test_canonicalization_ignores_key_order(): void
    {
        $customerId = (string) Str::uuid();

        $a = [
            'customer' => [
                'name' => 'Ana',
                'email' => 'ana@example.test',
                'phone' => '+570000000001',
                'document_type' => 'CC',
                'document_number' => '1001',
            ],
            'delivery' => [
                'mode' => 'COORDINATED_SHIPPING',
                'address' => 'Calle 1 # 2-3',
            ],
        ];

        $b = [
            'delivery' => [
                'address' => 'Calle 1 # 2-3',
                'mode' => 'COORDINATED_SHIPPING',
            ],
            'customer' => [
                'document_number' => '1001',
                'phone' => '+570000000001',
                'email' => 'ana@example.test',
                'document_type' => 'CC',
                'name' => 'Ana',
            ],
        ];

        $this->assertSame(
            $this->service->fingerprint($customerId, $a),
            $this->service->fingerprint($customerId, $b),
        );
    }

    public function test_delivery_address_change_changes_hash(): void
    {
        $customerId = (string) Str::uuid();
        $base = $this->checkoutPayload([
            'delivery' => [
                'mode' => 'COORDINATED_SHIPPING',
                'address' => 'Calle A 10',
            ],
        ]);
        $changed = $this->checkoutPayload([
            'delivery' => [
                'mode' => 'COORDINATED_SHIPPING',
                'address' => 'Calle B 20',
            ],
        ]);

        $this->assertNotSame(
            $this->service->fingerprint($customerId, $base),
            $this->service->fingerprint($customerId, $changed),
        );
    }

    public function test_customer_email_change_changes_hash(): void
    {
        $customerId = (string) Str::uuid();
        $base = $this->checkoutPayload();
        $changed = $this->checkoutPayload([
            'customer' => ['email' => 'changed@example.test'],
        ]);

        $this->assertNotSame(
            $this->service->fingerprint($customerId, $base),
            $this->service->fingerprint($customerId, $changed),
        );
    }

    public function test_pickup_missing_address_and_null_address_same_hash(): void
    {
        $customerId = (string) Str::uuid();

        $omitted = [
            'customer' => $this->checkoutPayload()['customer'],
            'delivery' => ['mode' => 'PICKUP'],
        ];
        $explicitNull = [
            'customer' => $this->checkoutPayload()['customer'],
            'delivery' => ['mode' => 'PICKUP', 'address' => null],
        ];

        $this->assertSame(
            $this->service->fingerprint($customerId, $omitted),
            $this->service->fingerprint($customerId, $explicitNull),
        );
    }

    public function test_validate_key_rejects_malformed(): void
    {
        foreach ([null, '', '   ', 'short', str_repeat('x', 129)] as $bad) {
            try {
                $this->service->validateKey($bad);
                $this->fail('Expected MALFORMED_REQUEST for key: '.var_export($bad, true));
            } catch (ApiException $e) {
                $this->assertSame(400, $e->status);
                $this->assertSame('MALFORMED_REQUEST', $e->errorCode);
            }
        }

        $this->assertSame('valid-key-01', $this->service->validateKey('  valid-key-01  '));
    }

    public function test_fingerprint_includes_customer_id_isolation(): void
    {
        $payload = $this->checkoutPayload();

        $this->assertNotSame(
            $this->service->fingerprint((string) Str::uuid(), $payload),
            $this->service->fingerprint((string) Str::uuid(), $payload),
        );
    }

    public function test_resolve_after_unique_violation_replays_matching_order(): void
    {
        $customer = $this->makeCustomer();
        $payload = $this->checkoutPayload();
        $key = 'idem-key-race-ok-1';
        $hash = $this->service->fingerprint($customer->id, $payload);
        $order = $this->createOrder($customer, $key, $hash);

        $resolved = $this->service->resolveAfterUniqueViolation($customer, $key, $hash);

        $this->assertSame($order->id, $resolved->id);
        $this->assertTrue($resolved->relationLoaded('items'));
    }

    public function test_is_idempotency_key_unique_violation_detects_23505(): void
    {
        $matching = new QueryException(
            'pgsql',
            'insert into "orders"',
            [],
            new \Exception('SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "orders_idempotency_key_unique"'),
        );
        $matching->errorInfo = ['23505', '23505', 'duplicate key value violates unique constraint "orders_idempotency_key_unique"'];

        $other = new QueryException(
            'pgsql',
            'insert into "orders"',
            [],
            new \Exception('SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "orders_order_number_unique"'),
        );
        $other->errorInfo = ['23505', '23505', 'duplicate key value violates unique constraint "orders_order_number_unique"'];

        $notUnique = new QueryException(
            'pgsql',
            'insert into "orders"',
            [],
            new \Exception('SQLSTATE[23503]: foreign key violation'),
        );
        $notUnique->errorInfo = ['23503', '23503', 'foreign key'];

        $this->assertTrue($this->service->isIdempotencyKeyUniqueViolation($matching));
        $this->assertFalse($this->service->isIdempotencyKeyUniqueViolation($other));
        $this->assertFalse($this->service->isIdempotencyKeyUniqueViolation($notUnique));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{customer: array<string, string>, delivery: array<string, mixed>}
     */
    private function checkoutPayload(array $overrides = []): array
    {
        $base = [
            'customer' => [
                'name' => 'Cliente Demo',
                'email' => 'cliente.demo@example.test',
                'phone' => '+570000000000',
                'document_type' => 'CC',
                'document_number' => '1000000000',
            ],
            'delivery' => [
                'mode' => 'PICKUP',
                'address' => null,
            ],
        ];

        if (isset($overrides['customer']) && is_array($overrides['customer'])) {
            $base['customer'] = array_merge($base['customer'], $overrides['customer']);
            unset($overrides['customer']);
        }
        if (isset($overrides['delivery']) && is_array($overrides['delivery'])) {
            $base['delivery'] = array_merge($base['delivery'], $overrides['delivery']);
            unset($overrides['delivery']);
        }

        return array_merge($base, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCustomer(array $overrides = []): Customer
    {
        $user = User::factory()->customer()->create([
            'email' => $overrides['email'] ?? ('cust-'.Str::lower(Str::random(6)).'@example.test'),
        ]);

        return Customer::query()->create(array_merge([
            'user_id' => $user->id,
            'name' => 'Cliente Sintetico',
            'email' => $user->email,
            'phone' => '+57-300-000-0000',
            'document_type' => 'CC',
            'document_number' => (string) random_int(1000000000, 1999999999),
        ], $overrides));
    }

    private function createOrder(Customer $customer, string $key, string $hash): Order
    {
        return Order::query()->create([
            'order_number' => 'RTR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'COP',
            'customer_name' => 'Cliente Demo',
            'customer_email' => 'cliente.demo@example.test',
            'customer_phone' => '+570000000000',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1000000000',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '10000.00',
            'tax_total' => '1900.00',
            'total' => '11900.00',
            'idempotency_key' => $key,
            'idempotency_request_hash' => $hash,
        ]);
    }
}
