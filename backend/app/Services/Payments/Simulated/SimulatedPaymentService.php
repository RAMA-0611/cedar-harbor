<?php

declare(strict_types=1);

namespace App\Services\Payments\Simulated;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\PaymentSettlementService;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Pasarela simulada: recorre el flujo completo (pago → resultado → asiento del pedido/inventario)
 * usando el MISMO servicio de asiento que el webhook de Wompi.
 */
final class SimulatedPaymentService
{
    public const MESSAGES = [
        SimulatedCards::APPROVED => 'Pago aprobado. Tu pedido quedó pagado.',
        SimulatedCards::DECLINED => 'El banco rechazó la tarjeta. No se realizó ningún cobro.',
        SimulatedCards::INSUFFICIENT_FUNDS => 'Fondos insuficientes en la tarjeta. No se realizó ningún cobro.',
        SimulatedCards::ERROR => 'Ocurrió un error al procesar el pago. No se realizó ningún cobro.',
        SimulatedCards::PENDING => 'El pago está pendiente de verificación. Confírmalo para continuar.',
    ];

    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentSettlementService $settlement,
    ) {}

    public static function enabled(): bool
    {
        return config('payments.provider') === 'simulated';
    }

    /**
     * @param  array{card_number: string, card_holder: string, expiry: string, cvc: string}  $card
     * @return array<string, mixed>
     */
    public function pay(User $user, string $orderId, array $card): array
    {
        $order = $this->orders->getOwned($user, $orderId);
        $payment = $this->pendingPayment($order);
        $outcome = SimulatedCards::outcomeFor($card['card_number']);
        $last4 = substr($card['card_number'], -4);

        if ($outcome === SimulatedCards::PENDING) {
            $payment->provider_transaction_id = 'SIM-PENDING-'.Str::upper(Str::random(10));
            $payment->save();

            return $this->result($order->refresh(), $payment->refresh(), $outcome, $last4, null);
        }

        $status = $outcome === SimulatedCards::APPROVED ? 'APPROVED' : ($outcome === SimulatedCards::ERROR ? 'ERROR' : 'DECLINED');
        $settled = $this->settle($payment, $order, $status);

        return $this->result($order->refresh(), $payment->refresh(), $outcome, $last4, $settled['reason']);
    }

    /**
     * Cierra un pago PENDING (simula la verificación 3-D Secure del banco).
     *
     * @return array<string, mixed>
     */
    public function complete(User $user, string $orderId, bool $approve): array
    {
        $order = $this->orders->getOwned($user, $orderId);
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'SIMULATED')
            ->orderByDesc('created_at')
            ->first();

        if ($payment === null || $payment->status !== 'PENDING'
            || ! str_starts_with((string) $payment->provider_transaction_id, 'SIM-PENDING-')) {
            throw ApiException::conflict('NO_PENDING_PAYMENT', 'No hay un pago pendiente de verificación para este pedido.');
        }

        $outcome = $approve ? SimulatedCards::APPROVED : SimulatedCards::DECLINED;
        $settled = $this->settle($payment, $order, $approve ? 'APPROVED' : 'DECLINED');

        return $this->result($order->refresh(), $payment->refresh(), $outcome, null, $settled['reason']);
    }

    private function pendingPayment(Order $order): Payment
    {
        if ($order->status !== 'PENDING_PAYMENT') {
            throw ApiException::conflict(
                'INVALID_ORDER_STATE',
                'El pedido ya no está pendiente de pago.',
                ['status' => $order->status],
            );
        }

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'SIMULATED')
            ->where('status', 'PENDING')
            ->orderByDesc('created_at')
            ->first();

        if ($payment === null) {
            throw ApiException::unprocessable('PAYMENT_NOT_FOUND', 'Primero inicia el pago del pedido.');
        }

        return $payment;
    }

    /** @return array{applied: bool, reason: string} */
    private function settle(Payment $payment, Order $order, string $status): array
    {
        $txId = 'SIM-'.Str::upper(Str::random(12));
        if (str_starts_with((string) $payment->provider_transaction_id, 'SIM-PENDING-')) {
            // Se libera el id temporal para que el id final quede en la columna única.
            $payment->provider_transaction_id = null;
            $payment->save();
        }

        return $this->settlement->settleFromWompiTransaction([
            'id' => $txId,
            'reference' => (string) $payment->merchant_reference,
            'amount_in_cents' => Money::toCents(Money::format((string) $order->total, 2)),
            'currency' => (string) $order->currency,
            'status' => $status,
            'environment' => 'test',
        ]);
    }

    /** @return array<string, mixed> */
    private function result(Order $order, Payment $payment, string $outcome, ?string $last4, ?string $reason): array
    {
        return [
            'outcome' => $outcome,
            'message' => self::MESSAGES[$outcome],
            'order_id' => $order->id,
            'order_status' => $order->status,
            'payment_status' => $payment->status,
            'transaction_id' => $payment->provider_transaction_id,
            'card_last4' => $last4,
            'settlement_reason' => $reason,
            'can_retry' => in_array($outcome, [SimulatedCards::DECLINED, SimulatedCards::INSUFFICIENT_FUNDS, SimulatedCards::ERROR], true),
        ];
    }
}
