<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockReservation;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminOrderService
{
    private const STATUSES = [
        'DRAFT',
        'PENDING_PAYMENT',
        'PAID',
        'FULFILLED',
        'FAILED',
        'EXPIRED',
        'CANCELLED',
    ];

    public function __construct(private readonly OrderService $orders)
    {
    }

    /**
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $page = $this->parsePositiveIntQuery($request, 'page', 1, null);
        $perPage = $this->parsePositiveIntQuery($request, 'per_page', 20, 100);

        $query = Order::query()->orderByDesc('created_at');

        $status = $this->optionalStringQuery($request, 'status');
        if ($status !== null) {
            if (! in_array($status, self::STATUSES, true)) {
                throw new BadRequestHttpException('Query parameter status is invalid.');
            }
            $query->where('status', $status);
        }

        $customerId = $this->optionalStringQuery($request, 'customer_id');
        if ($customerId !== null) {
            if (! Str::isUuid($customerId)) {
                throw new BadRequestHttpException('Query parameter customer_id must be a UUID.');
            }
            $query->where('customer_id', $customerId);
        }

        $search = $this->optionalStringQuery($request, 'search');
        if ($search !== null) {
            if (mb_strlen($search) > 120) {
                throw new BadRequestHttpException('Query parameter search must be at most 120 characters.');
            }
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder->where('order_number', 'ilike', $like)
                    ->orWhere('customer_name', 'ilike', $like)
                    ->orWhere('customer_email', 'ilike', $like);
            });
        }

        return $query->paginate(perPage: $perPage, page: $page);
    }

    public function get(string $orderId): Order
    {
        $this->assertUuid($orderId);

        $order = Order::query()->whereKey($orderId)->with('items')->first();
        if ($order === null) {
            throw new NotFoundHttpException('The requested resource was not found.');
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $orderId, array $payload): Order
    {
        $this->assertUuid($orderId);

        return DB::transaction(function () use ($orderId, $payload) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new NotFoundHttpException('The requested resource was not found.');
            }

            $status = $payload['status'] ?? null;
            unset($payload['status']);

            if ($payload !== []) {
                $mode = $payload['delivery_mode'] ?? $order->delivery_mode;
                if ($mode === 'PICKUP') {
                    $payload['delivery_address'] = null;
                } elseif (array_key_exists('delivery_address', $payload)) {
                    $payload['delivery_address'] = is_string($payload['delivery_address'])
                        ? trim($payload['delivery_address'])
                        : $payload['delivery_address'];
                }

                $allowed = [
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'customer_document_type',
                    'customer_document_number',
                    'delivery_mode',
                    'delivery_address',
                ];
                $order->fill(array_intersect_key($payload, array_flip($allowed)));
                $order->save();
            }

            if ($status === 'CANCELLED') {
                return $this->orders->applyCancellation($order->fresh(), allowIdempotentCancelled: true);
            }

            return $order->fresh()->load('items');
        });
    }

    public function destroy(string $orderId): void
    {
        $this->assertUuid($orderId);

        DB::transaction(function () use ($orderId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                throw new NotFoundHttpException('The requested resource was not found.');
            }

            if ($order->status !== 'CANCELLED') {
                throw ApiException::conflict(
                    'INVALID_ORDER_STATE',
                    'Only cancelled orders can be deleted.',
                    ['status' => $order->status],
                );
            }

            if (Payment::query()->where('order_id', $order->id)->exists()) {
                throw ApiException::conflict(
                    'RESOURCE_IN_USE',
                    'Order cannot be deleted while payments exist.',
                    ['reason' => 'payments'],
                );
            }

            if (WebhookEvent::query()->where('order_id', $order->id)->exists()) {
                throw ApiException::conflict(
                    'RESOURCE_IN_USE',
                    'Order cannot be deleted while webhook events exist.',
                    ['reason' => 'webhook_events'],
                );
            }

            $blockingReservations = StockReservation::query()
                ->where('order_id', $order->id)
                ->whereIn('status', ['ACTIVE', 'CONSUMED'])
                ->lockForUpdate()
                ->get(['id', 'status']);

            if ($blockingReservations->isNotEmpty()) {
                throw ApiException::conflict(
                    'RESOURCE_IN_USE',
                    'Order cannot be deleted while stock reservations are active or consumed.',
                    [
                        'reason' => 'stock_reservations',
                        'statuses' => $blockingReservations->pluck('status')->unique()->values()->all(),
                    ],
                );
            }

            OrderItem::query()->where('order_id', $order->id)->delete();
            $order->delete();
        });
    }

    private function assertUuid(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw new BadRequestHttpException('orderId must be a UUID.');
        }
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

    private function optionalStringQuery(Request $request, string $key): ?string
    {
        if (! $request->query->has($key)) {
            return null;
        }

        $raw = $request->query($key);
        if (! is_string($raw)) {
            throw new BadRequestHttpException("Query parameter {$key} must be a string.");
        }

        return $raw;
    }
}
