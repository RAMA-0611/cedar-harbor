<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\Simulated\SimulatedPaymentService;
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
     *   redirect_url: ?string
     * }
     */
    public function createForOrder(User $user, string $orderId): array
    {
        $simulated = SimulatedPaymentService::enabled();
        $provider = $simulated ? 'SIMULATED' : 'WOMPI';

        if (! $simulated) {
            try {
                $this->wompi->assertSandboxKeysConfigured();
            } catch (\RuntimeException $e) {
                throw ApiException::serviceUnavailable(
                    'PAYMENT_NOT_CONFIGURED',
                    'El pago en línea no está configurado. Defina las llaves sandbox de Wompi en backend/.env o use PAYMENT_PROVIDER=simulated.',
                );
            }
        }

        $order = $this->orders->getOwned($user, $orderId);

        return DB::transaction(function () use ($order, $simulated, $provider) {
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
                ->where('provider', $provider)
                ->where('status', 'PENDING')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            $amount = Money::format((string) $locked->total, 2);
            $currency = (string) $locked->currency;
            $amountInCents = Money::toCents($amount);
            $redirectUrl = $this->wompi->redirectUrl();

            if ($existing !== null) {
                $payment = $existing;
                $reference = (string) $payment->merchant_reference;

                if ($payment->redirect_url !== $redirectUrl) {
                    $payment->redirect_url = $redirectUrl;
                    $payment->save();
                }
            } else {
                $reference = 'RTR-PAY-'.Str::upper(str_replace('-', '', (string) Str::uuid()));
                $payment = Payment::query()->create([
                    'order_id' => $locked->id,
                    'provider' => $provider,
                    'provider_reference' => $reference,
                    'merchant_reference' => $reference,
                    'status' => 'PENDING',
                    'amount' => $amount,
                    'currency' => $currency,
                    'redirect_url' => $redirectUrl,
                    'expires_at' => now()->addMinutes(30),
                ]);
            }

            if ($simulated) {
                return [
                    'payment_id' => $payment->id,
                    'provider' => 'SIMULATED',
                    'public_key' => '',
                    'reference' => $reference,
                    'amount_in_cents' => $amountInCents,
                    'currency' => $currency,
                    'integrity_signature' => '',
                    'checkout_url' => '',
                    'redirect_url' => null,
                ];
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
                'redirect_url' => $redirectUrl,
            ];
        });
    }
}
