<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('condition', 40)->nullable();
            $table->string('unit', 60)->nullable();
            $table->decimal('price', 18, 2);
            $table->char('currency', 3)->default('COP');
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('category_id');
            $table->index('is_active');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_tax_rate_non_negative CHECK (tax_rate >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_quantity_non_negative CHECK (stock_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
