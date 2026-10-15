<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(private readonly CartService $carts)
    {
    }

    /**
     * Soft preflight: always returns a CheckoutValidation-shaped array (HTTP 200).
     * Request field validation is handled by CheckoutRequest (422).
     *
     * @param  array{customer: array<string, string>, delivery: array{mode: string, address: ?string}}  $payload
     * @return array{valid: bool, cart: array<string, mixed>|null, validation_errors: list<array{code: string, message: string}>, totals: array{subtotal: string, tax_total: string, total: string, currency: string}}
     */
    public function validate(User $user, array $payload): array
    {
        $customer = $this->carts->requireCustomer($user);

        return DB::transaction(function () use ($customer) {
            $cart = $this->carts->findActiveCart($customer);

            if ($cart === null) {
                return $this->invalidEmptyResult(null);
            }

            $this->carts->expireStaleReservationsForCart($cart);
            $cart = $this->carts->loadCartWithRelations($cart->fresh());

            $errors = $this->collectBusinessErrors($cart);
            $totals = $this->carts->calculateTotals($cart);
            $cartPayload = $this->cartArray($cart, $totals);

            return [
                'valid' => $errors === [],
                'cart' => $cartPayload,
                'validation_errors' => $errors,
                'totals' => [
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'total' => $totals['total'],
                    'currency' => $cart->currency,
                ],
            ];
        });
    }

    /**
     * Hard revalidation for order creation (throws ApiException).
     * Caller must already hold locks as needed.
     *
     * @return array{cart: Cart, totals: array{subtotal: string, tax_total: string, total: string, items: list<array<string, mixed>>}}
     */
    public function assertReadyForOrder(Customer $customer): array
    {
        $cart = $this->carts->findActiveCart($customer);

        if ($cart === null) {
            throw ApiException::unprocessable('CART_EMPTY', 'Cart has no items.');
        }

        $this->carts->expireStaleReservationsForCart($cart);
        $cart = $this->carts->loadCartWithRelations($cart->fresh());

        if ($cart->items->isEmpty()) {
            throw ApiException::unprocessable('CART_EMPTY', 'Cart has no items.');
        }

        foreach ($cart->items as $item) {
            $reservation = $this->activeReservationForItem($item->id, $cart->id, $item->product_id, (int) $item->quantity);
            if ($reservation === null) {
                throw ApiException::unprocessable(
                    'RESERVATION_EXPIRED',
                    'One or more reservations have expired.',
                    ['cart_item_id' => $item->id],
                );
            }
        }

        $productIds = $cart->items->pluck('product_id')->unique()->sort()->values();
        foreach ($productIds as $productId) {
            /** @var Product $product */
            $product = Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
            $this->carts->expireStaleReservationsForCart($cart);

            $activeSum = (int) StockReservation::query()
                ->where('product_id', $product->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            if ($activeSum > (int) $product->stock_quantity) {
                throw ApiException::unprocessable(
                    'INSUFFICIENT_STOCK',
                    'Requested quantity exceeds available stock.',
                    ['product_id' => $product->id],
                );
            }

            $line = $cart->items->firstWhere('product_id', $product->id);
            if ($line !== null) {
                $own = $this->activeReservationForItem($line->id, $cart->id, $product->id, (int) $line->quantity);
                if ($own === null) {
                    throw ApiException::unprocessable(
                        'RESERVATION_EXPIRED',
                        'One or more reservations have expired.',
                        ['cart_item_id' => $line->id],
                    );
                }
            }
        }

        $totals = $this->carts->calculateTotals($cart);

        return compact('cart', 'totals');
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    private function collectBusinessErrors(Cart $cart): array
    {
        $errors = [];

        if ($cart->items->isEmpty()) {
            $errors[] = ['code' => 'CART_EMPTY', 'message' => 'Cart has no items.'];

            return $errors;
        }

        foreach ($cart->items as $item) {
            if ($this->activeReservationForItem($item->id, $cart->id, $item->product_id, (int) $item->quantity) === null) {
                $errors[] = [
                    'code' => 'RESERVATION_EXPIRED',
                    'message' => 'One or more reservations have expired.',
                ];
                break;
            }
        }

        foreach ($cart->items as $item) {
            /** @var Product $product */
            $product = $item->product;
            $activeSum = (int) StockReservation::query()
                ->where('product_id', $product->id)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->sum('quantity');

            if ($activeSum > (int) $product->stock_quantity) {
                $errors[] = [
                    'code' => 'INSUFFICIENT_STOCK',
                    'message' => 'Requested quantity exceeds available stock.',
                ];
                break;
            }
        }

        return $errors;
    }

    private function activeReservationForItem(
        string $cartItemId,
        string $cartId,
        string $productId,
        int $quantity,
    ): ?StockReservation {
        return StockReservation::query()
            ->where('cart_item_id', $cartItemId)
            ->where('cart_id', $cartId)
            ->where('product_id', $productId)
            ->where('quantity', $quantity)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * @param  array{subtotal: string, tax_total: string, total: string, items: list<array<string, mixed>>}  $totals
     * @return array<string, mixed>
     */
    private function cartArray(Cart $cart, array $totals): array
    {
        return [
            'id' => $cart->id,
            'items' => $totals['items'],
            'subtotal' => $totals['subtotal'],
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'currency' => $cart->currency,
            'expires_at' => optional($cart->expires_at)?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $cart
     * @return array{valid: bool, cart: array<string, mixed>|null, validation_errors: list<array{code: string, message: string}>, totals: array{subtotal: string, tax_total: string, total: string, currency: string}}
     */
    private function invalidEmptyResult(?array $cart): array
    {
        return [
            'valid' => false,
            'cart' => $cart,
            'validation_errors' => [
                ['code' => 'CART_EMPTY', 'message' => 'Cart has no items.'],
            ],
            'totals' => [
                'subtotal' => '0.00',
                'tax_total' => '0.00',
                'total' => '0.00',
                'currency' => 'COP',
            ],
        ];
    }
}
