<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateOrderRequest;
use App\Http\Resources\AdminOrderResource;
use App\Services\AdminOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(private readonly AdminOrderService $orders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orders->paginate($request);

        return response()->json([
            'data' => AdminOrderResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $orderId): AdminOrderResource
    {
        return new AdminOrderResource($this->orders->get($orderId));
    }

    public function update(AdminUpdateOrderRequest $request, string $orderId): AdminOrderResource
    {
        return new AdminOrderResource($this->orders->update($orderId, $request->validated()));
    }

    public function destroy(string $orderId): Response
    {
        $this->orders->destroy($orderId);

        return response()->noContent();
    }
}
