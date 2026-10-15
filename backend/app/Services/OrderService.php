<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\Orders\OrderIdempotencyService;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly CheckoutService $checkout,
        private readonly OrderIdempotencyService $idempotency,
    ) {
    }

    /**
     * @param  array{customer: array<string, string>, delivery: array{mode: string, address: ?string}}  $payload
     */
    public function create(User $user, array $payload, ?string $idempotencyKeyHeader): Order
    {
        $key = $this->idempotency->validateKey($idempotencyKeyHeader);
        $customer = $this->carts->requireCustomer($user);
        $hash = $this->idempotency->fingerprint($customer->id, $payload);

        $replay = $this->idempotency->findReplay($customer, $key, $hash);
        if ($replay !== null) {
            return $replay;
        }

        try {
            return DB::transaction(function () use ($customer, $payload, $key, $hash) {
                Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

                $ready = $this->checkout->assertReadyForOrder($customer);
                $cart = $ready['cart'];
                $totals = $ready['totals'];

                // Lock cart items and products in stable id order.
                $itemIds = $cart->items->pluck('id')->sort()->values();
                foreach ($itemIds as $itemId) {
                    $cart->items()->whereKey($itemId)->lockForUpdate()->first();
                }

                $productIds = $cart->items->pluck('product_id')->unique()->sort()->values();
                foreach ($productIds as $productId) {
                    Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
                }

                $reservationIds = StockReservation::query()
                    ->where('cart_id', $cart->id)
                    ->where('status', 'ACTIVE')
                    ->where('expires_at', '>', now())
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($reservationIds as $reservationId) {
                    StockReservation::query()->whereKey($reservationId)->lockForUpdate()->first();
                }

                // Re-validate after locks.
                $ready = $this->checkout->assertReadyForOrder($customer);
                $cart = $ready['cart'];
                $totals = $ready['totals'];

                $order = Order::query()->create([
                    'order_number' => $this->generateOrderNumber(),
                    'customer_id' => $customer->id,
                    'status' => 'PENDING_PAYMENT',
                    'currency' => $cart->currency,
                    'customer_name' => $payload['customer']['name'],
                    'customer_email' => $payload['customer']['email'],
                    'customer_phone' => $payload['customer']['phone'],
                    'customer_document_type' => $payload['customer']['document_type'],
                    'customer_document_number' => $payload['customer']['document_number'],
                    'delivery_mode' => $payload['delivery']['mode'],
                    'delivery_address' => $payload['delivery']['mode'] === 'PICKUP'
                        ? null
                        : $payload['delivery']['address'],
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                    'idempotency_key' => $key,
                    'idempotency_request_hash' => $hash,
                ]);

                foreach ($cart->items as $item) {
                    /** @var Product $product */
                    $product = $item->product;
                    $unitPrice = Money::format((string) $product->price, 2);
                    $taxRate = Money::format((string) $product->tax_rate, 4);
                    $lineSubtotal = Money::mul($unitPrice, (string) $item->quantity, 2);
                    $taxAmount = Money::mul($lineSubtotal, $taxRate, 2);
                    $lineTotal = Money::add($lineSubtotal, $taxAmount, 2);

                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'product_name' => $product->name,
                        'quantity' => (int) $item->quantity,
                        'unit_price' => $unitPrice,
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'line_subtotal' => $lineSubtotal,
                        'line_total' => $lineTotal,
                    ]);
                }

                StockReservation::query()
                    ->where('cart_id', $cart->id)
                    ->where('status', 'ACTIVE')
                    ->where('expires_at', '>', now())
                    ->update(['order_id' => $order->id]);

                $cart->status = 'CONVERTED';
                $cart->save();

                return $order->fresh()->load('items');
            });
        } catch (QueryException $e) {
            if ($this->idempotency->isIdempotencyKeyUniqueViolation($e)) {
                return $this->idempotency->resolveAfterUniqueViolation($customer, $key, $hash);
            }

            throw $e;
        }
    }

    public function getOwned(User $user, string $orderId): Order
    {
        $this->assertUuid($orderId);
        $customer = $this->carts->requireCustomer($user);

        $order = Order::query()
            ->whereKey($orderId)
            ->where('customer_id', $customer->id)
            ->with('items')
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('The requested resource was not found.');
        }

        return $order;
    }

    /**
     * @return array{order_id: string, status: string, updated_at: string}
     */
    public function status(User $user, string $orderId): array
    {
        $order = $this->getOwned($user, $orderId);

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'updated_at' => $order->updated_at->toISOString(),
        ];
    }

    public function cancel(User $user, string $orderId): Order
    {
        $this->assertUuid($orderId);
        $customer = $this->carts->requireCustomer($user);

        return DB::transaction(function () use ($customer, $orderId) {
            $order = Order::query()
                ->whereKey($orderId)
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new NotFoundHttpException('The requested resource was not found.');
            }

            if (! in_array($order->status, ['DRAFT', 'PENDING_PAYMENT'], true)) {
                throw ApiException::conflict(
                    'INVALID_ORDER_STATE',
                    'Order cannot be cancelled from its current state.',
                    ['status' => $order->status],
                );
            }

            $order->status = 'CANCELLED';
            $order->cancelled_at = now();
            $order->save();

            StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'RELEASED',
                    'released_at' => now(),
                ]);

            return $order->fresh()->load('items');
        });
    }

    private function generateOrderNumber(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'RTR-'.now()->format('Ymd').'-'.Str::upper(bin2hex(random_bytes(4)));

            if (! Order::query()->where('order_number', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Extremely unlikely collision path — still unique enough for VARCHAR(30).
        return 'RTR-'.now()->format('Ymd').'-'.Str::upper(substr(str_replace('-', '', (string) Str::uuid()), 0, 8));
    }

    private function assertUuid(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw new BadRequestHttpException('orderId must be a UUID.');
        }
    }
}
