<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->orders->create(
            $request->user(),
            $request->checkoutPayload(),
            $request->header('Idempotency-Key'),
        );

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $orderId): OrderResource
    {
        return new OrderResource($this->orders->getOwned($request->user(), $orderId));
    }

    public function status(Request $request, string $orderId): JsonResponse
    {
        return response()->json($this->orders->status($request->user(), $orderId));
    }

    public function cancel(Request $request, string $orderId): OrderResource
    {
        return new OrderResource($this->orders->cancel($request->user(), $orderId));
    }
}
