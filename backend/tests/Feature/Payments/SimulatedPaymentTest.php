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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SimulatedPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disableCookieEncryption();
        $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/',
            'Accept' => 'application/json',
        ]);
        config(['payments.provider' => 'simulated']);
    }

    private function startPayment(): array
    {
        $ctx = $this->seedBaseOrder();
        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/payment-intent')
            ->assertCreated()
            ->assertJsonPath('provider', 'SIMULATED');

        return $ctx;
    }

    private function card(string $number, array $over = []): array
    {
        return array_merge([
            'card_holder' => 'Ana Perez',
            'card_number' => $number,
            'expiry' => '12/40',
            'cvc' => '123',
        ], $over);
    }

    public function test_public_config_lists_test_cards(): void
    {
        $this->getJson('/api/payments/config')
            ->assertOk()
            ->assertJsonPath('simulated', true)
            ->assertJsonCount(5, 'test_cards')
            ->assertJsonPath('test_cards.0.number', '4242424242424242');

        foreach ($this->getJson('/api/payments/config')->json('test_cards') as $card) {
            $this->assertIsString($card['number']);
        }
    }

    public function test_approved_card_pays_order_and_consumes_stock(): void
    {
        $ctx = $this->startPayment();

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/simulated-payment', $this->card('4242 4242 4242 4242'))
            ->assertOk()
            ->assertJsonPath('outcome', 'APPROVED')
            ->assertJsonPath('order_status', 'PAID')
            ->assertJsonPath('payment_status', 'APPROVED')
            ->assertJsonPath('card_last4', '4242')
            ->assertJsonMissingPath('card_number');

        $this->assertSame('PAID', $ctx['order']->refresh()->status);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $ctx['order']->id, 'status' => 'CONSUMED']);
        $this->assertSame(9, (int) Product::query()->firstOrFail()->stock_quantity);
        $this->assertStringStartsWith('SIM-', (string) Payment::query()->firstOrFail()->provider_transaction_id);
    }

    public static function failingCards(): array
    {
        return [
            'rechazada' => ['4000000000000002', 'DECLINED', 'DECLINED'],
            'fondos insuficientes' => ['4000000000009995', 'INSUFFICIENT_FUNDS', 'DECLINED'],
            'error' => ['4000000000000119', 'ERROR', 'ERROR'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('failingCards')]
    public function test_failing_cards_fail_order_and_release_stock(string $number, string $outcome, string $paymentStatus): void
    {
        $ctx = $this->startPayment();

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/simulated-payment', $this->card($number))
            ->assertOk()
            ->assertJsonPath('outcome', $outcome)
            ->assertJsonPath('order_status', 'FAILED')
            ->assertJsonPath('payment_status', $paymentStatus)
            ->assertJsonPath('can_retry', true);

        $this->assertDatabaseHas('stock_reservations', ['order_id' => $ctx['order']->id, 'status' => 'RELEASED']);
        $this->assertSame(10, (int) Product::query()->firstOrFail()->stock_quantity);
    }

    public function test_pending_card_can_be_completed_as_approved_or_declined(): void
    {
        $ctx = $this->startPayment();
        $url = '/api/orders/'.$ctx['order']->id.'/simulated-payment';

        $this->actingAs($ctx['user'])->postJson($url, $this->card('4000000000003220'))
            ->assertOk()
            ->assertJsonPath('outcome', 'PENDING')
            ->assertJsonPath('order_status', 'PENDING_PAYMENT');

        $this->postJson($url.'/complete', ['decision' => 'APPROVED'])
            ->assertOk()
            ->assertJsonPath('order_status', 'PAID');

        $this->postJson($url.'/complete', ['decision' => 'APPROVED'])->assertStatus(409);
    }

    public function test_pending_card_declined_on_completion(): void
    {
        $ctx = $this->startPayment();
        $url = '/api/orders/'.$ctx['order']->id.'/simulated-payment';

        $this->actingAs($ctx['user'])->postJson($url, $this->card('4000000000003220'))->assertOk();
        $this->postJson($url.'/complete', ['decision' => 'DECLINED'])
            ->assertOk()
            ->assertJsonPath('order_status', 'FAILED');
    }

    public function test_paid_order_cannot_be_paid_twice(): void
    {
        $ctx = $this->startPayment();
        $url = '/api/orders/'.$ctx['order']->id.'/simulated-payment';

        $this->actingAs($ctx['user'])->postJson($url, $this->card('4242424242424242'))->assertOk();
        $this->postJson($url, $this->card('4242424242424242'))->assertStatus(409);
        $this->assertSame(9, (int) Product::query()->firstOrFail()->stock_quantity);
    }

    public function test_requires_payment_intent_first(): void
    {
        $ctx = $this->seedBaseOrder();

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/simulated-payment', $this->card('4242424242424242'))
            ->assertStatus(422);
    }

    public static function invalidCards(): array
    {
        return [
            'luhn inválido' => [['card_number' => '4242424242424241'], 'card_number'],
            'letras' => [['card_number' => '4242abcd42424242'], 'card_number'],
            'vencida' => [['expiry' => '01/20'], 'expiry'],
            'formato fecha' => [['expiry' => '1/2030'], 'expiry'],
            'mes 13' => [['expiry' => '13/30'], 'expiry'],
            'cvc corto' => [['cvc' => '12'], 'cvc'],
            'cvc con letras' => [['cvc' => '12a'], 'cvc'],
            'titular con número' => [['card_holder' => 'Ana 123'], 'card_holder'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidCards')]
    public function test_invalid_card_data_is_rejected(array $override, string $field): void
    {
        $ctx = $this->startPayment();

        $res = $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/simulated-payment', $this->card('4242424242424242', $override));

        $res->assertStatus(422);
        $this->assertArrayHasKey($field, $res->json('details.errors') ?? []);
        $this->assertSame('PENDING_PAYMENT', $ctx['order']->refresh()->status);
    }

    public function test_other_customer_cannot_pay_foreign_order(): void
    {
        $ctx = $this->startPayment();
        $other = $this->seedBaseOrder();

        $this->actingAs($other['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/simulated-payment', $this->card('4242424242424242'))
            ->assertStatus(404);
    }

    public function test_simulator_is_unavailable_when_provider_is_wompi(): void
    {
        $ctx = $this->startPayment();
        config(['payments.provider' => 'wompi']);

        $this->actingAs($ctx['user'])
            ->postJson('/api/orders/'.$ctx['order']->id.'/simulated-payment', $this->card('4242424242424242'))
            ->assertStatus(404);
        $this->getJson('/api/payments/config')->assertJsonPath('simulated', false)->assertJsonCount(0, 'test_cards');
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/orders/'.Str::uuid().'/simulated-payment', $this->card('4242424242424242'))
            ->assertStatus(401);
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
