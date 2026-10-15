<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20);
            $table->string('external_event_id', 255);
            $table->string('event_type', 100)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();
            $table->string('processing_status', 30)->default('RECEIVED');
            $table->string('processing_result', 255)->nullable();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['provider', 'external_event_id']);
            $table->index('order_id');
            $table->index('payment_id');
            $table->index(['processing_status', 'received_at']);
        });

        DB::statement("ALTER TABLE webhook_events ADD CONSTRAINT webhook_events_provider_check CHECK (provider IN ('WOMPI', 'MERCADOPAGO'))");
        DB::statement("ALTER TABLE webhook_events ADD CONSTRAINT webhook_events_processing_status_check CHECK (processing_status IN ('RECEIVED', 'PROCESSED', 'IGNORED_DUPLICATE', 'FAILED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
