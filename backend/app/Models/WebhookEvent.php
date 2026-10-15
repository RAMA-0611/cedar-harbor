<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider',
        'external_event_id',
        'event_type',
        'signature_verified',
        'payload',
        'received_at',
        'processed_at',
        'processing_status',
        'processing_result',
        'order_id',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
