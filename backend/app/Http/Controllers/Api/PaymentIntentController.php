<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class PaymentIntentController extends Controller
{
    public function __construct(
        private readonly PaymentIntentService $intents,
    ) {}

    public function store(Request $request, string $orderId): JsonResponse
    {
        try {
            $payload = $this->intents->createForOrder($request->user(), $orderId);
        } catch (RuntimeException $e) {
            throw new ServiceUnavailableHttpException(null, $e->getMessage());
        }

        return response()->json($payload, 201);
    }
}
