<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\Payments\PaymentSettlementService;
use App\Services\Payments\Wompi\WompiEventSignature;
use App\Services\Payments\Wompi\WompiIntegrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WompiSettlementTest extends TestCase
{
    use RefreshDatabase;

    private string $eventsSecret = 'test_events_secret_value';

    private string $integritySecret = 'test_integrity_secret_value';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.wompi.env' => 'sandbox',
            'services.wompi.public_key' => 'pub_test_demo_key',
            'services.wompi.private_key' => 'prv_test_demo_key',
            'services.wompi.integrity_secret' => $this->integritySecret,
            'services.wompi.events_secret' => $this->eventsSecret,
        ]);
    }

    public function test_integrity_signature_is_deterministic(): void
    {
        $sig = WompiIntegrity::sign('REF-1', 12345, 'COP', $this->integritySecret);
        $this->assertSame(
            hash('sha256', 'REF-1'.'12345'.'COP'.$this->integritySecret),
            $sig,
        );
    }

    public function test_webhook_invalid_signature_is_rejected(): void
    {
        $response = $this->postJson('/api/webhooks/payments/wompi', [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => 'tx1',
                    'reference' => 'x',
                    'amount_in_cents' => 100,
                    'currency' => 'COP',
                    'status' => 'APPROVED',
                ],
            ],
            'environment' => 'test',
            'signature' => [
                'properties' => ['transaction.id'],
                'checksum' => 'deadbeef',
            ],
            'timestamp' => 1710000000,
            'sent_at' => '2024-03-09T12:00:00.000Z',
        ], [
            'X-Event-Checksum' => 'deadbeef',
        ]);

        $response->assertStatus(401);
    }

    public function test_approved_settlement_is_exactly_once(): void
    {
        $ctx = $this->seedPendingOrder(stock: 10, qty: 2, total: '20000.00');
        $service = app(PaymentSettlementService::class);

        $tx = [
            'id' => 'tx-approved-1',
            'reference' => $ctx['reference'],
            'amount_in_cents' => 2000000,
            'currency' => 'COP',
            'status' => 'APPROVED',
            'environment' => 'test',
        ];

        $first = $service->settleFromWompiTransaction($tx);
        $second = $service->settleFromWompiTransaction($tx);

        $this->assertTrue($first['applied']);
        $this->assertFalse($second['applied']);
        $this->assertSame('ALREADY_SETTLED', $second['reason']);

        $ctx['product']->refresh();
        $this->assertSame(8, (int) $ctx['product']->stock_quantity);
        $this->assertSame('PAID', $ctx['order']->fresh()->status);
        $this->assertSame('APPROVED', $ctx['payment']->fresh()->status);
        $this->assertSame('CONSUMED', $ctx['reservation']->fresh()->status);
    }

    public function test_declined_releases_reservation_without_stock_change(): void
    {
        $ctx = $this->seedPendingOrder(stock: 7, qty: 1, total: '10000.00');
        $service = app(PaymentSettlementService::class);

        $result = $service->settleFromWompiTransaction([
            'id' => 'tx-declined-1',
            'reference' => $ctx['reference'],
            'amount_in_cents' => 1000000,
            'currency' => 'COP',
            'status' => 'DECLINED',
            'environment' => 'test',
        ]);

        $this->assertTrue($result['applied']);
        $ctx['product']->refresh();
        $this->assertSame(7, (int) $ctx['product']->stock_quantity);
        $this->assertSame('FAILED', $ctx['order']->fresh()->status);
        $this->assertSame('DECLINED', $ctx['payment']->fresh()->status);
        $this->assertSame('RELEASED', $ctx['reservation']->fresh()->status);
    }

    public function test_amount_mismatch_does_not_settle(): void
    {
        $ctx = $this->seedPendingOrder(stock: 5, qty: 1, total: '10000.00');
        $service = app(PaymentSettlementService::class);

        $result = $service->settleFromWompiTransaction([
            'id' => 'tx-mismatch',
            'reference' => $ctx['reference'],
            'amount_in_cents' => 999,
            'currency' => 'COP',
            'status' => 'APPROVED',
            'environment' => 'test',
        ]);

        $this->assertFalse($result['applied']);
        $this->assertSame('AMOUNT_MISMATCH', $result['reason']);
        $this->assertSame('PENDING_PAYMENT', $ctx['order']->fresh()->status);
        $this->assertSame(5, (int) $ctx['product']->fresh()->stock_quantity);
    }

    public function test_webhook_duplicate_is_idempotent(): void
    {
        $ctx = $this->seedPendingOrder(stock: 4, qty: 1, total: '5000.00');
        $timestamp = 1710000000;
        $txId = 'tx-wh-1';
        $properties = ['transaction.id', 'transaction.status', 'transaction.amount_in_cents'];
        $concat = $txId.'APPROVED'.'500000'.(string) $timestamp.$this->eventsSecret;
        $checksum = hash('sha256', $concat);

        $payload = [
            'event' => 'transaction.updated',
            'data' => [
                'transaction' => [
                    'id' => $txId,
                    'reference' => $ctx['reference'],
                    'amount_in_cents' => 500000,
                    'currency' => 'COP',
                    'status' => 'APPROVED',
                ],
            ],
            'environment' => 'test',
            'signature' => [
                'properties' => $properties,
                'checksum' => $checksum,
            ],
            'timestamp' => $timestamp,
            'sent_at' => '2024-03-09T12:00:00.000Z',
        ];

        $this->assertTrue(WompiEventSignature::verify($payload, $checksum, $this->eventsSecret));

        $first = $this->postJson('/api/webhooks/payments/wompi', $payload, [
            'X-Event-Checksum' => $checksum,
        ]);
        $first->assertOk()->assertJsonPath('ok', true);

        $second = $this->postJson('/api/webhooks/payments/wompi', $payload, [
            'X-Event-Checksum' => $checksum,
        ]);
        $second->assertOk()->assertJsonPath('duplicate', true);

        $this->assertSame(3, (int) $ctx['product']->fresh()->stock_quantity);
        $this->assertSame('PAID', $ctx['order']->fresh()->status);
    }

    /**
     * @return array{order: Order, payment: Payment, product: Product, reservation: StockReservation, reference: string}
     */
    private function seedPendingOrder(int $stock, int $qty, string $total): array
    {
        $user = User::factory()->create([
            'role' => 'CUSTOMER',
            'email' => 'cust-'.Str::lower(Str::random(6)).'@example.test',
        ]);
        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'name' => 'Customer Demo',
            'email' => $user->email,
            'phone' => '3000000000',
            'document_type' => 'CC',
            'document_number' => '1000'.random_int(100, 999),
        ]);

        $category = Category::query()->create([
            'name' => 'Pay Cat',
            'slug' => 'pay-'.Str::lower(Str::random(5)),
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PAY-'.Str::upper(Str::random(5)),
            'name' => 'Payable Item',
            'price' => '10000.00',
            'currency' => 'COP',
            'tax_rate' => '0.0000',
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        $cart = Cart::query()->create([
            'customer_id' => $customer->id,
            'status' => 'CONVERTED',
            'currency' => 'COP',
            'expires_at' => now()->addHour(),
        ]);
        $item = CartItem::query()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $qty,
        ]);

        $order = Order::query()->create([
            'order_number' => 'RTR-TEST-'.Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'COP',
            'customer_name' => 'Customer Demo',
            'customer_email' => $user->email,
            'customer_phone' => '3000000000',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1234567',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => $total,
            'tax_total' => '0.00',
            'total' => $total,
            'idempotency_key' => (string) Str::uuid(),
            'idempotency_request_hash' => hash('sha256', 'x'),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => $qty,
            'unit_price' => '10000.00',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.00',
            'line_subtotal' => $total,
            'line_total' => $total,
        ]);

        $reservation = StockReservation::query()->create([
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $qty,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHour(),
        ]);

        $reference = 'RTR-PAY-'.Str::upper(Str::random(10));
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'provider' => 'WOMPI',
            'provider_reference' => $reference,
            'merchant_reference' => $reference,
            'status' => 'PENDING',
            'amount' => $total,
            'currency' => 'COP',
            'redirect_url' => 'http://127.0.0.1:3000/pago/resultado',
        ]);

        return compact('order', 'payment', 'product', 'reservation') + ['reference' => $reference];
    }
}
