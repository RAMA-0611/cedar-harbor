<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\SimulatedPaymentRequest;
use App\Services\Payments\Simulated\SimulatedCards;
use App\Services\Payments\Simulated\SimulatedPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SimulatedPaymentController extends Controller
{
    public function __construct(private readonly SimulatedPaymentService $payments) {}

    public function config(): JsonResponse
    {
        $cards = [];
        foreach (SimulatedCards::TEST_CARDS as $number => $info) {
            $cards[] = ['number' => (string) $number, 'outcome' => $info['outcome'], 'label' => $info['label']];
        }

        return response()->json([
            'provider' => config('payments.provider'),
            'simulated' => SimulatedPaymentService::enabled(),
            'test_cards' => SimulatedPaymentService::enabled() ? $cards : [],
        ]);
    }

    public function pay(SimulatedPaymentRequest $request, string $orderId): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json($this->payments->pay($request->user(), $orderId, $request->validated()));
    }

    public function complete(Request $request, string $orderId): JsonResponse
    {
        $this->ensureEnabled();
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,DECLINED']]);

        return response()->json($this->payments->complete($request->user(), $orderId, $data['decision'] === 'APPROVED'));
    }

    private function ensureEnabled(): void
    {
        if (! SimulatedPaymentService::enabled()) {
            throw new NotFoundHttpException('La pasarela simulada no está activa.');
        }
    }
}
