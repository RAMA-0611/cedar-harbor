<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockReservation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Unique settlement path for webhook + reconcile (exactly-once stock).
 */
final class PaymentSettlementService
{
    /**
     * @param  array{
     *   reference: string,
     *   amount_in_cents: int|string,
     *   currency: string,
     *   status: string,
     *   id?: string|null,
     *   environment?: string|null
     * }  $transaction
     * @return array{applied: bool, reason: string, order_id: ?string, payment_id: ?string}
     */
    public function settleFromWompiTransaction(array $transaction, ?callable $afterLock = null): array
    {
        $reference = trim((string) ($transaction['reference'] ?? ''));
        $status = strtoupper(trim((string) ($transaction['status'] ?? '')));
        $currency = strtoupper(trim((string) ($transaction['currency'] ?? '')));
        $amountInCents = (int) $transaction['amount_in_cents'];
        $providerTxId = isset($transaction['id']) ? trim((string) $transaction['id']) : null;
        $environment = isset($transaction['environment']) ? strtolower((string) $transaction['environment']) : null;

        if ($reference === '' || $status === '') {
            return ['applied' => false, 'reason' => 'MISSING_FIELDS', 'order_id' => null, 'payment_id' => null];
        }

        return DB::transaction(function () use (
            $reference,
            $status,
            $currency,
            $amountInCents,
            $providerTxId,
            $environment,
            $afterLock,
        ) {
            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('provider', 'WOMPI')
                ->where('merchant_reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return ['applied' => false, 'reason' => 'PAYMENT_NOT_FOUND', 'order_id' => null, 'payment_id' => null];
            }

            /** @var Order $order */
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($afterLock !== null) {
                $afterLock($order, $payment);
            }

            if ($order->status === 'PAID' && $payment->status === 'APPROVED') {
                return [
                    'applied' => false,
                    'reason' => 'ALREADY_SETTLED',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ];
            }

            if (in_array($order->status, ['PAID', 'CANCELLED'], true) && $status !== 'APPROVED') {
                return [
                    'applied' => false,
                    'reason' => 'ORDER_TERMINAL_PROTECTED',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ];
            }

            $expectedCents = Money::toCents(Money::format((string) $order->total, 2));
            if ($amountInCents !== $expectedCents) {
                $this->markIgnoredMismatch($payment, 'AMOUNT_mismatch');

                return [
                    'applied' => false,
                    'reason' => 'AMOUNT_MISMATCH',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ];
            }

            if ($currency !== strtoupper((string) $order->currency) || $currency !== 'COP') {
                $this->markIgnoredMismatch($payment, 'currency_mismatch');

                return [
                    'applied' => false,
                    'reason' => 'CURRENCY_MISMATCH',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ];
            }

            if ($environment !== null && ! in_array($environment, ['test', 'sandbox', 'dev'], true)) {
                $this->markIgnoredMismatch($payment, 'environment_mismatch');

                return [
                    'applied' => false,
                    'reason' => 'ENVIRONMENT_MISMATCH',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ];
            }

            if ($providerTxId !== null && $providerTxId !== '') {
                $payment->provider_transaction_id = $providerTxId;
                $payment->provider_reference = $providerTxId;
            }

            return match ($status) {
                'APPROVED' => $this->applyApproved($order, $payment),
                'DECLINED' => $this->applyFailed($order, $payment, 'DECLINED'),
                'ERROR' => $this->applyFailed($order, $payment, 'ERROR'),
                default => [
                    'applied' => false,
                    'reason' => 'STATUS_IGNORED_'.$status,
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ],
            };
        });
    }

    /**
     * @return array{applied: bool, reason: string, order_id: ?string, payment_id: ?string}
     */
    private function applyApproved(Order $order, Payment $payment): array
    {
        if ($payment->status === 'APPROVED' && $order->status === 'PAID') {
            return [
                'applied' => false,
                'reason' => 'ALREADY_SETTLED',
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ];
        }

        if ($order->status !== 'PENDING_PAYMENT') {
            throw new RuntimeException('Cannot approve payment for non-PENDING_PAYMENT order.');
        }

        $reservations = StockReservation::query()
            ->where('order_id', $order->id)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $productIds = $reservations->pluck('product_id')->unique()->sort()->values();
        foreach ($productIds as $productId) {
            Product::query()->whereKey($productId)->lockForUpdate()->firstOrFail();
        }

        foreach ($reservations as $reservation) {
            /** @var Product $product */
            $product = Product::query()->whereKey($reservation->product_id)->firstOrFail();
            $qty = (int) $reservation->quantity;
            if ($qty < 1) {
                throw new InvalidArgumentException('Invalid reservation quantity.');
            }
            if ((int) $product->stock_quantity < $qty) {
                throw new RuntimeException('Insufficient stock during settlement.');
            }
            $product->stock_quantity = (int) $product->stock_quantity - $qty;
            $product->save();

            $reservation->status = 'CONSUMED';
            $reservation->consumed_at = now();
            $reservation->save();
        }

        $payment->status = 'APPROVED';
        $payment->paid_at = now();
        $payment->failed_at = null;
        $payment->save();

        $order->status = 'PAID';
        $order->paid_at = now();
        $order->failed_at = null;
        $order->save();

        return [
            'applied' => true,
            'reason' => 'APPROVED',
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ];
    }

    /**
     * @return array{applied: bool, reason: string, order_id: ?string, payment_id: ?string}
     */
    private function applyFailed(Order $order, Payment $payment, string $paymentStatus): array
    {
        if ($order->status === 'PAID') {
            return [
                'applied' => false,
                'reason' => 'ORDER_TERMINAL_PROTECTED',
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ];
        }

        if ($payment->status === $paymentStatus && $order->status === 'FAILED') {
            return [
                'applied' => false,
                'reason' => 'ALREADY_FAILED',
                'order_id' => $order->id,
                'payment_id' => $payment->id,
            ];
        }

        $payment->status = $paymentStatus;
        $payment->failed_at = now();
        $payment->save();

        if ($order->status === 'PENDING_PAYMENT') {
            $order->status = 'FAILED';
            $order->failed_at = now();
            $order->save();

            StockReservation::query()
                ->where('order_id', $order->id)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'RELEASED',
                    'released_at' => now(),
                ]);
        }

        return [
            'applied' => true,
            'reason' => $paymentStatus,
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ];
    }

    private function markIgnoredMismatch(Payment $payment, string $reason): void
    {
        // Keep payment PENDING; do not mutate order. Evidence lives in webhook processing_result.
        $payment->last_reconciled_at = now();
        $payment->save();
        unset($reason);
    }
}
