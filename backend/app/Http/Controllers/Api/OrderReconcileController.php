<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payments\PaymentSettlementService;
use App\Services\Payments\Wompi\WompiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OrderReconcileController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly WompiClient $wompi,
        private readonly PaymentSettlementService $settlement,
    ) {}

    public function store(Request $request, string $orderId): JsonResponse
    {
        $order = $this->orders->getOwned($request->user(), $orderId);

        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('provider', 'WOMPI')
            ->orderByDesc('created_at')
            ->first();

        if ($payment === null) {
            throw ApiException::unprocessable('PAYMENT_NOT_FOUND', 'No Wompi payment found for order.');
        }

        $txId = $payment->provider_transaction_id;
        if ($txId === null || $txId === '') {
            $txId = $request->input('transaction_id');
        }
        if (! is_string($txId) || $txId === '') {
            throw ApiException::unprocessable(
                'TRANSACTION_ID_REQUIRED',
                'provider_transaction_id missing; pass transaction_id to reconcile.',
            );
        }

        $tx = $this->wompi->getTransaction($txId);
        $result = $this->settlement->settleFromWompiTransaction([
            'id' => (string) ($tx['id'] ?? $txId),
            'reference' => (string) ($tx['reference'] ?? ''),
            'amount_in_cents' => (int) ($tx['amount_in_cents'] ?? 0),
            'currency' => (string) ($tx['currency'] ?? ''),
            'status' => (string) ($tx['status'] ?? ''),
            'environment' => isset($tx['environment']) ? (string) $tx['environment'] : 'test',
        ]);

        $payment->last_reconciled_at = now();
        $payment->save();

        $fresh = $this->orders->getOwned($request->user(), $orderId);

        return response()->json([
            'settlement' => $result,
            'order' => (new OrderResource($fresh))->resolve(),
        ]);
    }
}
