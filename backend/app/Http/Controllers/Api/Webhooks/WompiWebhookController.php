<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentSettlementService;
use App\Services\Payments\Wompi\WompiConfig;
use App\Services\Payments\Wompi\WompiEventSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

final class WompiWebhookController extends Controller
{
    public function __construct(
        private readonly WompiConfig $config,
        private readonly PaymentSettlementService $settlement,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $checksum = (string) ($request->header('X-Event-Checksum') ?? '');

        if (! $this->config->hasEventsSecret()) {
            return response()->json(['ok' => false, 'reason' => 'EVENTS_SECRET_MISSING'], 503);
        }

        $verified = WompiEventSignature::verify($payload, $checksum, $this->config->eventsSecret());
        if (! $verified) {
            return response()->json(['ok' => false, 'reason' => 'INVALID_SIGNATURE'], 401);
        }

        $eventType = is_string($payload['event'] ?? null) ? (string) $payload['event'] : 'unknown';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $transaction = is_array($data['transaction'] ?? null) ? $data['transaction'] : $data;

        $externalId = $this->resolveExternalEventId($payload, $transaction, $checksum);

        try {
            $existing = WebhookEvent::query()
                ->where('provider', 'WOMPI')
                ->where('external_event_id', $externalId)
                ->first();

            if ($existing !== null) {
                return response()->json([
                    'ok' => true,
                    'duplicate' => true,
                    'processing_status' => $existing->processing_status,
                ]);
            }

            $result = DB::transaction(function () use ($payload, $eventType, $externalId, $transaction) {
                $event = WebhookEvent::query()->create([
                    'provider' => 'WOMPI',
                    'external_event_id' => $externalId,
                    'event_type' => $eventType,
                    'signature_verified' => true,
                    'payload' => $payload,
                    'received_at' => now(),
                    'processing_status' => 'RECEIVED',
                ]);

                $settlement = $this->settlement->settleFromWompiTransaction([
                    'id' => isset($transaction['id']) ? (string) $transaction['id'] : null,
                    'reference' => (string) ($transaction['reference'] ?? ''),
                    'amount_in_cents' => (int) ($transaction['amount_in_cents'] ?? 0),
                    'currency' => (string) ($transaction['currency'] ?? ''),
                    'status' => (string) ($transaction['status'] ?? ''),
                    'environment' => isset($transaction['environment']) ? (string) $transaction['environment'] : 'test',
                ]);

                $event->processing_status = 'PROCESSED';
                $event->processed_at = now();
                $event->processing_result = $settlement['reason'];
                $event->order_id = $settlement['order_id'];
                $event->payment_id = $settlement['payment_id'];
                $event->save();

                return $settlement;
            });
        } catch (Throwable $e) {
            // Unique violation → duplicate retry.
            if (str_contains($e->getMessage(), 'webhook_events_provider_external_event_id_unique')
                || str_contains($e->getMessage(), 'duplicate key')) {
                return response()->json(['ok' => true, 'duplicate' => true]);
            }

            WebhookEvent::query()->updateOrCreate(
                [
                    'provider' => 'WOMPI',
                    'external_event_id' => $externalId,
                ],
                [
                    'event_type' => $eventType,
                    'signature_verified' => true,
                    'payload' => $payload,
                    'received_at' => now(),
                    'processed_at' => now(),
                    'processing_status' => 'FAILED',
                    'processing_result' => 'exception',
                ],
            );

            return response()->json(['ok' => false, 'reason' => 'PROCESSING_FAILED'], 500);
        }

        return response()->json(['ok' => true, 'settlement' => $result]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $transaction
     */
    private function resolveExternalEventId(array $payload, array $transaction, string $checksum): string
    {
        if (isset($payload['id']) && is_string($payload['id']) && $payload['id'] !== '') {
            return $payload['id'];
        }

        $type = is_string($payload['event'] ?? null) ? (string) $payload['event'] : 'event';
        $txId = isset($transaction['id']) ? (string) $transaction['id'] : 'notx';
        $ts = '';
        if (isset($payload['signature']) && is_array($payload['signature']) && isset($payload['signature']['timestamp'])) {
            $ts = (string) $payload['signature']['timestamp'];
        }

        return hash('sha256', 'WOMPI|'.$type.'|'.$txId.'|'.$ts.'|'.$checksum);
    }
}
