<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminProductResource;
use App\Models\Product;
use App\Services\Products\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProductImageController extends Controller
{
    public function __construct(
        private readonly ProductImageService $images,
    ) {}

    public function store(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('image');
        if ($file === null) {
            return response()->json([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Image file is required.',
                'details' => ['image' => ['required']],
                'trace_id' => $request->headers->get('X-Trace-Id'),
            ], 422);
        }

        $product = $this->images->store($product, $file);

        return (new AdminProductResource($product))
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(Product $product): Response
    {
        $this->images->clear($product);

        return response()->noContent();
    }
}
