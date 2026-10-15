<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\AdminProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()->orderBy('name')->get();

        return AdminProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return (new AdminProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): AdminProductResource
    {
        return new AdminProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): AdminProductResource
    {
        $product->fill($request->validated());
        $product->save();

        return new AdminProductResource($product->refresh());
    }

    public function destroy(Product $product): Response
    {
        if (
            $product->cartItems()->exists()
            || $product->stockReservations()->exists()
            || $product->orderItems()->exists()
        ) {
            throw ApiException::conflict(
                'RESOURCE_IN_USE',
                'Product cannot be deleted because it is referenced by domain data.',
                ['resource' => 'product', 'id' => $product->id],
            );
        }

        $product->delete();

        return response()->noContent();
    }
}
