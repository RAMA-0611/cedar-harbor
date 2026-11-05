<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('merchant_reference', 255)->nullable()->after('provider_reference');
            $table->string('provider_transaction_id', 255)->nullable()->after('merchant_reference');
            $table->timestampTz('last_reconciled_at')->nullable()->after('failed_at');
        });

        DB::statement('CREATE UNIQUE INDEX payments_provider_merchant_reference_unique ON payments (provider, merchant_reference) WHERE merchant_reference IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX payments_provider_transaction_id_unique ON payments (provider, provider_transaction_id) WHERE provider_transaction_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_provider_merchant_reference_unique');
        DB::statement('DROP INDEX IF EXISTS payments_provider_transaction_id_unique');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['merchant_reference', 'provider_transaction_id', 'last_reconciled_at']);
        });
    }
};
