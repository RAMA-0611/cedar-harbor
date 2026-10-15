<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 30)->unique();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 20)->default('DRAFT');
            $table->char('currency', 3);
            $table->string('customer_name', 160);
            $table->string('customer_email', 254);
            $table->string('customer_phone', 30);
            $table->string('customer_document_type', 10);
            $table->string('customer_document_number', 30);
            $table->string('delivery_mode', 30);
            $table->string('delivery_address', 255)->nullable();
            $table->decimal('subtotal', 18, 2);
            $table->decimal('tax_total', 18, 2);
            $table->decimal('total', 18, 2);
            $table->string('idempotency_key', 255)->unique();
            $table->string('idempotency_request_hash', 128);
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('expired_at')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('DRAFT', 'PENDING_PAYMENT', 'PAID', 'FULFILLED', 'FAILED', 'EXPIRED', 'CANCELLED'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_mode_check CHECK (delivery_mode IN ('PICKUP', 'COORDINATED_SHIPPING'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_delivery_address_check CHECK (
            (delivery_mode = 'PICKUP' AND delivery_address IS NULL)
            OR
            (delivery_mode = 'COORDINATED_SHIPPING' AND delivery_address IS NOT NULL AND btrim(delivery_address) <> '')
        )");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_non_negative CHECK (subtotal >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_tax_total_non_negative CHECK (tax_total >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_non_negative CHECK (total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
