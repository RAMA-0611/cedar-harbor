<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider', 20);
            $table->string('provider_reference', 255)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->text('redirect_url')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();

            $table->index('order_id');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('WOMPI', 'MERCADOPAGO'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('PENDING', 'APPROVED', 'DECLINED', 'EXPIRED', 'CANCELLED', 'ERROR'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_non_negative CHECK (amount >= 0)');
        // UNIQUE (provider, provider_reference) only when provider_reference IS NOT NULL
        DB::statement('CREATE UNIQUE INDEX payments_provider_provider_reference_unique ON payments (provider, provider_reference) WHERE provider_reference IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
