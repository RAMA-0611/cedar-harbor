<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('status', 20)->default('ACTIVE');
            $table->char('currency', 3);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['customer_id', 'status']);
        });

        DB::statement("ALTER TABLE carts ADD CONSTRAINT carts_status_check CHECK (status IN ('ACTIVE', 'CONVERTED', 'EXPIRED', 'ABANDONED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
