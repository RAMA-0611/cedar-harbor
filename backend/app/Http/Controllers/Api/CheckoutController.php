<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout)
    {
    }

    public function validateCheckout(CheckoutRequest $request): JsonResponse
    {
        $result = $this->checkout->validate($request->user(), $request->checkoutPayload());

        return response()->json($result);
    }
}
