<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\Wompi\WompiConfig;
use App\Services\Payments\Wompi\WompiIntegrity;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PaymentIntentService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly WompiConfig $wompi,
    ) {}

    /**
     * @return array{
     *   payment_id: string,
     *   provider: string,
     *   public_key: string,
     *   reference: string,
     *   amount_in_cents: int,
     *   currency: string,
     *   integrity_signature: string,
     *   checkout_url: string,
     *   redirect_url: string
     * }
     */
    public function createForOrder(User $user, string $orderId): array
    {
        $this->wompi->assertSandboxKeysConfigured();

        $order = $this->orders->getOwned($user, $orderId);

        return DB::transaction(function () use ($order) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'PENDING_PAYMENT') {
                throw ApiException::conflict(
                    'INVALID_ORDER_STATE',
                    'Payment intent requires PENDING_PAYMENT order.',
                    ['status' => $locked->status],
                );
            }

            $existing = Payment::query()
                ->where('order_id', $locked->id)
                ->where('provider', 'WOMPI')
                ->where('status', 'PENDING')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            $amount = Money::format((string) $locked->total, 2);
            $currency = (string) $locked->currency;
            $amountInCents = Money::toCents($amount);
            $redirectUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/')
                .'/pago/resultado';

            if ($existing !== null) {
                $payment = $existing;
                $reference = (string) $payment->merchant_reference;
            } else {
                $reference = 'RTR-PAY-'.Str::upper(str_replace('-', '', (string) Str::uuid()));
                $payment = Payment::query()->create([
                    'order_id' => $locked->id,
                    'provider' => 'WOMPI',
                    'provider_reference' => $reference,
                    'merchant_reference' => $reference,
                    'status' => 'PENDING',
                    'amount' => $amount,
                    'currency' => $currency,
                    'redirect_url' => $redirectUrl,
                    'expires_at' => now()->addMinutes(30),
                ]);
            }

            $signature = WompiIntegrity::sign(
                $reference,
                $amountInCents,
                $currency,
                $this->wompi->integritySecret(),
            );

            return [
                'payment_id' => $payment->id,
                'provider' => 'WOMPI',
                'public_key' => $this->wompi->publicKey(),
                'reference' => $reference,
                'amount_in_cents' => $amountInCents,
                'currency' => $currency,
                'integrity_signature' => $signature,
                'checkout_url' => $this->wompi->checkoutUrl(),
                'redirect_url' => (string) $payment->redirect_url,
            ];
        });
    }
}
