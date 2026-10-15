<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductSummaryResource;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = $this->parsePositiveIntQuery($request, 'page', 1, null);
        $perPage = $this->parsePositiveIntQuery($request, 'per_page', 20, 100);
        $q = $this->parseSearchQuery($request);

        $query = $this->visibleCatalogQuery()
            ->withSum([
                'stockReservations as active_reserved_quantity' => function (Builder $builder) {
                    $builder->where('status', 'ACTIVE')
                        ->where('expires_at', '>', now());
                },
            ], 'quantity')
            ->orderBy('name');

        if ($q !== null) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder->where('name', 'ilike', $like)
                    ->orWhere('sku', 'ilike', $like);
            });
        }

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return response()->json([
            'data' => ProductSummaryResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $productId): JsonResponse
    {
        if (! Str::isUuid($productId)) {
            throw new BadRequestHttpException('Product id must be a valid UUID.');
        }

        $product = $this->visibleCatalogQuery()
            ->withSum([
                'stockReservations as active_reserved_quantity' => function (Builder $builder) {
                    $builder->where('status', 'ACTIVE')
                        ->where('expires_at', '>', now());
                },
            ], 'quantity')
            ->whereKey($productId)
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException('The requested resource was not found.');
        }

        // OpenAPI ProductSummary is the root body (no Resource `data` wrapper).
        return response()->json((new ProductSummaryResource($product))->resolve());
    }

    private function visibleCatalogQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('category', function (Builder $builder) {
                $builder->where('is_active', true);
            });
    }

    private function parsePositiveIntQuery(Request $request, string $key, int $default, ?int $max): int
    {
        if (! $request->query->has($key)) {
            return $default;
        }

        $raw = $request->query($key);

        if (is_int($raw)) {
            $value = $raw;
        } elseif (is_string($raw) && preg_match('/^[1-9]\d*$/', $raw) === 1) {
            $value = (int) $raw;
        } else {
            throw new BadRequestHttpException("Query parameter {$key} must be an integer.");
        }

        if ($value < 1 || ($max !== null && $value > $max)) {
            throw new BadRequestHttpException("Query parameter {$key} is out of range.");
        }

        return $value;
    }

    private function parseSearchQuery(Request $request): ?string
    {
        if (! $request->query->has('q')) {
            return null;
        }

        $raw = $request->query('q');

        if (! is_string($raw)) {
            throw new BadRequestHttpException('Query parameter q must be a string.');
        }

        if (mb_strlen($raw) > 120) {
            throw new BadRequestHttpException('Query parameter q must be at most 120 characters.');
        }

        return $raw;
    }
}
