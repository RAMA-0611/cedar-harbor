<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_provider_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('WOMPI', 'MERCADOPAGO', 'SIMULATED'))");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM payments WHERE provider = 'SIMULATED'");
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_provider_check');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_provider_check CHECK (provider IN ('WOMPI', 'MERCADOPAGO'))");
    }
};
