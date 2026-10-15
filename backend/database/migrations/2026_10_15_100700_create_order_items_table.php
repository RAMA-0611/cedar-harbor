<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('sku', 64);
            $table->string('product_name', 255);
            $table->integer('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('line_subtotal', 18, 2);
            $table->decimal('line_total', 18, 2);
            $table->timestampsTz();

            $table->index('order_id');
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_non_negative CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_tax_rate_non_negative CHECK (tax_rate IS NULL OR tax_rate >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_tax_amount_non_negative CHECK (tax_amount >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_subtotal_non_negative CHECK (line_subtotal >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_non_negative CHECK (line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
