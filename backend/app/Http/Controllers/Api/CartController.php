<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $carts)
    {
    }

    public function show(Request $request): CartResource
    {
        return new CartResource($this->carts->getCart($request->user()));
    }

    public function storeItem(AddCartItemRequest $request): CartResource
    {
        $data = $request->validated();

        $cart = $this->carts->addItem(
            $request->user(),
            $data['product_id'],
            (int) $data['quantity'],
        );

        return new CartResource($cart);
    }

    public function updateItem(UpdateCartItemRequest $request, string $cartItemId): CartResource
    {
        $cart = $this->carts->updateItem(
            $request->user(),
            $cartItemId,
            (int) $request->validated('quantity'),
        );

        return new CartResource($cart);
    }

    public function destroyItem(Request $request, string $cartItemId): Response
    {
        $this->carts->removeItem($request->user(), $cartItemId);

        return response()->noContent();
    }
}
