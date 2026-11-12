<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los usuarios CUSTOMER creados desde el panel de administración no tienen
        // teléfono ni documento; el perfil de cliente debe poder existir sin ellos.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->change();
            $table->string('document_type', 10)->nullable()->change();
            $table->string('document_number', 30)->nullable()->change();
        });

        // Reparar usuarios CUSTOMER existentes que quedaron sin perfil de cliente.
        $users = DB::table('users')
            ->whereRaw("upper(role) = 'CUSTOMER'")
            ->whereNotIn('id', DB::table('customers')->whereNotNull('user_id')->select('user_id'))
            ->get(['id', 'name', 'email']);

        foreach ($users as $user) {
            DB::table('customers')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No se revierte: volver a NOT NULL fallaría con filas sin teléfono o documento.
    }
};
