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
use App\Services\Payments\Wompi\WompiIntegrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    private string $integritySecret = 'test_integrity_secret_value';

    protected function setUp(): void
    {
        parent::setUp();

        $this->disableCookieEncryption();
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'Accept' => 'application/json',
        ]);

        config([
            'services.wompi.env' => 'sandbox',
            'services.wompi.public_key' => 'pub_test_demo_key',
            'services.wompi.private_key' => 'prv_test_demo_key',
            'services.wompi.integrity_secret' => $this->integritySecret,
            'services.wompi.events_secret' => 'test_events_secret_value',
            'services.wompi.redirect_url' => '',
            'services.wompi.checkout_url' => 'https://checkout.wompi.co/p/',
        ]);
    }

    public function test_empty_redirect_url_returns_null(): void
    {
        $ctx = $this->seedPendingOrderWithoutPayment();

        $response = $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent');

        $response->assertCreated()
            ->assertJsonPath('redirect_url', null)
            ->assertJsonPath('provider', 'WOMPI')
            ->assertJsonPath('currency', 'COP');

        $this->assertNull($response->json('redirect_url'));
        $this->assertDatabaseHas('payments', [
            'id' => $response->json('payment_id'),
            'redirect_url' => null,
        ]);
    }

    public function test_reuses_pending_payment_and_clears_localhost_redirect(): void
    {
        $ctx = $this->seedPendingOrderWithLocalhostPayment();
        $paymentId = $ctx['payment']->id;
        $reference = $ctx['reference'];

        $response = $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent');

        $response->assertCreated()
            ->assertJsonPath('payment_id', $paymentId)
            ->assertJsonPath('reference', $reference)
            ->assertJsonPath('redirect_url', null);

        $this->assertSame(1, Payment::query()->where('order_id', $ctx['order']->id)->count());
        $this->assertNull($ctx['payment']->fresh()->redirect_url);
        $this->assertSame($reference, $ctx['payment']->fresh()->merchant_reference);
    }

    public function test_missing_wompi_credentials_return_clear_503(): void
    {
        config([
            'services.wompi.public_key' => '',
            'services.wompi.private_key' => '',
            'services.wompi.integrity_secret' => '',
        ]);
        $ctx = $this->seedPendingOrderWithoutPayment();

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent')
            ->assertStatus(503)
            ->assertJsonPath('code', 'PAYMENT_NOT_CONFIGURED');
    }

    public function test_https_redirect_url_is_returned(): void
    {
        config(['services.wompi.redirect_url' => 'https://example.test/pago/resultado']);
        $ctx = $this->seedPendingOrderWithoutPayment();

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent')
            ->assertCreated()
            ->assertJsonPath('redirect_url', 'https://example.test/pago/resultado');
    }

    public function test_local_http_redirect_url_is_rejected(): void
    {
        config(['services.wompi.redirect_url' => 'http://127.0.0.1:3000/pago/resultado']);
        $ctx = $this->seedPendingOrderWithoutPayment();

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent')
            ->assertStatus(500);
    }

    public function test_integrity_fields_stable_for_same_payment(): void
    {
        $ctx = $this->seedPendingOrderWithLocalhostPayment();

        $first = $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent')
            ->assertCreated();

        $second = $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent')
            ->assertCreated();

        $this->assertSame($first->json('payment_id'), $second->json('payment_id'));
        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertSame($first->json('amount_in_cents'), $second->json('amount_in_cents'));
        $this->assertSame($first->json('currency'), $second->json('currency'));
        $this->assertSame($first->json('integrity_signature'), $second->json('integrity_signature'));

        $expected = WompiIntegrity::sign(
            (string) $first->json('reference'),
            (int) $first->json('amount_in_cents'),
            (string) $first->json('currency'),
            $this->integritySecret,
        );
        $this->assertSame($expected, $first->json('integrity_signature'));
    }

    /**
     * @return array{user: User, order: Order}
     */
    private function seedPendingOrderWithoutPayment(): array
    {
        $pair = $this->seedBaseOrder();

        return ['user' => $pair['user'], 'order' => $pair['order']];
    }

    /**
     * @return array{user: User, order: Order, payment: Payment, reference: string}
     */
    private function seedPendingOrderWithLocalhostPayment(): array
    {
        $pair = $this->seedBaseOrder();
        $reference = 'RTR-PAY-'.Str::upper(Str::random(10));
        $payment = Payment::query()->create([
            'order_id' => $pair['order']->id,
            'provider' => 'WOMPI',
            'provider_reference' => $reference,
            'merchant_reference' => $reference,
            'status' => 'PENDING',
            'amount' => $pair['order']->total,
            'currency' => 'COP',
            'redirect_url' => 'http://127.0.0.1:3000/pago/resultado',
        ]);

        return [
            'user' => $pair['user'],
            'order' => $pair['order'],
            'payment' => $payment,
            'reference' => $reference,
        ];
    }

    /**
     * @return array{user: User, order: Order}
     */
    private function seedBaseOrder(): array
    {
        $user = User::factory()->create([
            'role' => 'CUSTOMER',
            'email' => 'pi-'.Str::lower(Str::random(6)).'@example.test',
        ]);
        $customer = Customer::query()->create([
            'user_id' => $user->id,
            'name' => 'PI Customer',
            'email' => $user->email,
            'phone' => '3000000000',
            'document_type' => 'CC',
            'document_number' => '1000'.random_int(100, 999),
        ]);

        $category = Category::query()->create([
            'name' => 'PI Cat',
            'slug' => 'pi-'.Str::lower(Str::random(5)),
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PI-'.Str::upper(Str::random(5)),
            'name' => 'Payable Item',
            'price' => '10000.00',
            'currency' => 'COP',
            'tax_rate' => '0.0000',
            'stock_quantity' => 10,
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
            'quantity' => 1,
        ]);

        $order = Order::query()->create([
            'order_number' => 'RTR-PI-'.Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'status' => 'PENDING_PAYMENT',
            'currency' => 'COP',
            'customer_name' => 'PI Customer',
            'customer_email' => $user->email,
            'customer_phone' => '3000000000',
            'customer_document_type' => 'CC',
            'customer_document_number' => '1234567',
            'delivery_mode' => 'PICKUP',
            'delivery_address' => null,
            'subtotal' => '10000.00',
            'tax_total' => '0.00',
            'total' => '10000.00',
            'idempotency_key' => (string) Str::uuid(),
            'idempotency_request_hash' => hash('sha256', 'pi'),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => '10000.00',
            'tax_rate' => '0.0000',
            'tax_amount' => '0.00',
            'line_subtotal' => '10000.00',
            'line_total' => '10000.00',
        ]);

        StockReservation::query()->create([
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 'ACTIVE',
            'expires_at' => now()->addHour(),
        ]);

        return compact('user', 'order');
    }
}
