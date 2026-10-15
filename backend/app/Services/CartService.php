<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartService
{
    public function requireCustomer(User $user): Customer
    {
        $customer = $user->customer;

        if ($customer === null) {
            throw ApiException::conflict(
                'CUSTOMER_PROFILE_REQUIRED',
                'A customer profile is required to use the cart.',
            );
        }

        return $customer;
    }

    public function getCart(User $user): Cart
    {
        $customer = $this->requireCustomer($user);

        return DB::transaction(function () use ($customer) {
            $cart = $this->resolveActiveCart($customer);
            $this->expireReservationsForCart($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function addItem(User $user, string $productId, int $quantity): Cart
    {
        $customer = $this->requireCustomer($user);

        return DB::transaction(function () use ($customer, $productId, $quantity) {
            $cart = $this->resolveActiveCart($customer);
            $this->expireReservationsForCart($cart);

            $product = $this->lockSellableProduct($productId);
            $this->expireReservationsForProduct($product->id);

            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            $desired = $quantity + (int) ($item?->quantity ?? 0);
            $this->assertStockAvailable($product, $desired, $item?->id);

            if ($item === null) {
                $item = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $desired,
                ]);
            } else {
                $item->quantity = $desired;
                $item->save();
            }

            $this->upsertActiveReservation($cart, $item, $product, $desired);
            $this->refreshCartExpiry($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function updateItem(User $user, string $cartItemId, int $quantity): Cart
    {
        $customer = $this->requireCustomer($user);

        return DB::transaction(function () use ($customer, $cartItemId, $quantity) {
            $cart = $this->resolveActiveCart($customer);
            $this->expireReservationsForCart($cart);

            $item = $this->ownedCartItem($cart, $cartItemId);
            $product = $this->lockSellableProduct($item->product_id);
            $this->expireReservationsForProduct($product->id);

            $this->assertStockAvailable($product, $quantity, $item->id);

            $item->quantity = $quantity;
            $item->save();

            $this->upsertActiveReservation($cart, $item, $product, $quantity);
            $this->refreshCartExpiry($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function removeItem(User $user, string $cartItemId): void
    {
        $customer = $this->requireCustomer($user);

        DB::transaction(function () use ($customer, $cartItemId) {
            $cart = $this->resolveActiveCart($customer);
            $this->expireReservationsForCart($cart);

            $item = $this->ownedCartItem($cart, $cartItemId);

            StockReservation::query()
                ->where('cart_item_id', $item->id)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'RELEASED',
                    'released_at' => now(),
                ]);

            $item->delete();
            $this->refreshCartExpiry($cart->fresh());
        });
    }

    /**
     * Maximum reachable quantity for a line: stock minus OTHER active reservations.
     */
    public function availableQuantityForLine(Product $product, ?string $excludeCartItemId = null): int
    {
        $reservedOthers = (int) StockReservation::query()
            ->where('product_id', $product->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->when($excludeCartItemId !== null, function (Builder $query) use ($excludeCartItemId) {
                $query->where(function (Builder $inner) use ($excludeCartItemId) {
                    $inner->where('cart_item_id', '!=', $excludeCartItemId)
                        ->orWhereNull('cart_item_id');
                });
            })
            ->sum('quantity');

        return max(0, (int) $product->stock_quantity - $reservedOthers);
    }

    /**
     * @return array{subtotal:string,tax_total:string,total:string,items:list<array<string,mixed>>}
     */
    public function calculateTotals(Cart $cart): array
    {
        $subtotal = '0.00';
        $taxTotal = '0.00';
        $items = [];

        foreach ($cart->items as $item) {
            /** @var Product $product */
            $product = $item->product;
            $unitPrice = Money::format((string) $product->price, 2);
            $taxRate = Money::format((string) $product->tax_rate, 4);
            $lineSubtotal = Money::mul($unitPrice, (string) $item->quantity, 2);
            $taxAmount = Money::mul($lineSubtotal, $taxRate, 2);
            $lineTotal = Money::add($lineSubtotal, $taxAmount, 2);

            $activeReservation = $item->stockReservations
                ->first(function (StockReservation $reservation) {
                    return $reservation->status === 'ACTIVE'
                        && $reservation->expires_at !== null
                        && $reservation->expires_at->isFuture();
                });

            $items[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => $unitPrice,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'available_quantity' => $this->availableQuantityForLine($product, $item->id),
                'reservation_expires_at' => $activeReservation?->expires_at?->toISOString(),
            ];

            $subtotal = Money::add($subtotal, $lineSubtotal, 2);
            $taxTotal = Money::add($taxTotal, $taxAmount, 2);
        }

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => Money::add($subtotal, $taxTotal, 2),
            'items' => $items,
        ];
    }

    private function resolveActiveCart(Customer $customer): Cart
    {
        // Serialize concurrent ACTIVE cart creation for the same customer.
        Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

        $cart = Cart::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'ACTIVE')
            ->orderBy('created_at')
            ->first();

        if ($cart !== null) {
            return $cart;
        }

        return Cart::query()->create([
            'customer_id' => $customer->id,
            'status' => 'ACTIVE',
            'currency' => 'COP',
            'expires_at' => null,
        ]);
    }

    private function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.product',
            'items.stockReservations',
        ]);
    }

    private function lockSellableProduct(string $productId): Product
    {
        $product = Product::query()
            ->whereKey($productId)
            ->lockForUpdate()
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException('The requested resource was not found.');
        }

        $product->loadMissing('category');

        if (! $product->is_active || $product->category === null || ! $product->category->is_active) {
            throw new NotFoundHttpException('The requested resource was not found.');
        }

        return $product;
    }

    private function ownedCartItem(Cart $cart, string $cartItemId): CartItem
    {
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->whereKey($cartItemId)
            ->first();

        if ($item === null) {
            throw new NotFoundHttpException('The requested resource was not found.');
        }

        return $item;
    }

    private function assertStockAvailable(Product $product, int $desired, ?string $excludeCartItemId): void
    {
        $available = $this->availableQuantityForLine($product, $excludeCartItemId);

        if ($desired > $available) {
            throw ApiException::unprocessable(
                'INSUFFICIENT_STOCK',
                'Requested quantity exceeds available stock.',
                [
                    'product_id' => $product->id,
                    'requested' => $desired,
                    'available_quantity' => $available,
                ],
            );
        }
    }

    private function upsertActiveReservation(
        Cart $cart,
        CartItem $item,
        Product $product,
        int $quantity,
    ): StockReservation {
        $expiresAt = now()->addMinutes((int) config('commerce.cart_reservation_minutes'));

        $active = StockReservation::query()
            ->where('cart_item_id', $item->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if ($active !== null) {
            $active->quantity = $quantity;
            $active->expires_at = $expiresAt;
            $active->product_id = $product->id;
            $active->cart_id = $cart->id;
            $active->save();

            return $active;
        }

        // Expire any stale ACTIVE rows for this line (clock skew / race).
        StockReservation::query()
            ->where('cart_item_id', $item->id)
            ->where('status', 'ACTIVE')
            ->update([
                'status' => 'EXPIRED',
            ]);

        return StockReservation::query()->create([
            'cart_id' => $cart->id,
            'cart_item_id' => $item->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'status' => 'ACTIVE',
            'expires_at' => $expiresAt,
        ]);
    }

    private function refreshCartExpiry(Cart $cart): void
    {
        $maxExpiry = StockReservation::query()
            ->where('cart_id', $cart->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', now())
            ->max('expires_at');

        $cart->expires_at = $maxExpiry ? Carbon::parse($maxExpiry) : null;
        $cart->save();
    }

    private function expireReservationsForCart(Cart $cart): void
    {
        StockReservation::query()
            ->where('cart_id', $cart->id)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'EXPIRED']);
    }

    private function expireReservationsForProduct(string $productId): void
    {
        StockReservation::query()
            ->where('product_id', $productId)
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'EXPIRED']);
    }
}
