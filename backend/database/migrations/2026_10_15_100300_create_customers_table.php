<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 160);
            $table->string('email', 254);
            $table->string('phone', 30);
            $table->string('document_type', 10);
            $table->string('document_number', 30);
            $table->timestampsTz();

            $table->index('email');
            $table->index(['document_type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
