<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cart_id')->constrained('carts')->restrictOnDelete();
            $table->foreignUuid('cart_item_id')->nullable()->constrained('cart_items')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->string('status', 20)->default('ACTIVE');
            $table->timestampTz('expires_at');
            $table->timestampTz('released_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();

            $table->index(['product_id', 'status', 'expires_at']);
            $table->index('order_id');
            $table->index('cart_id');
            $table->index('cart_item_id');
        });

        DB::statement("ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_status_check CHECK (status IN ('ACTIVE', 'CONSUMED', 'RELEASED', 'EXPIRED'))");
        DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
