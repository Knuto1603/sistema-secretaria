<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE solicitud MODIFY COLUMN estado ENUM('pendiente','en_revision','aprobada','rechazada','apelado','anulada') DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        // Antes de este cambio, anular() eliminaba la fila con soft delete (invisible en
        // listados). Al revertir, se restaura ese mismo efecto en vez de forzar un estado
        // sin equivalente en el ENUM anterior.
        DB::table('solicitud')->where('estado', 'anulada')->update([
            'estado' => 'rechazada',
            'deleted_at' => now(),
        ]);
        DB::statement("ALTER TABLE solicitud MODIFY COLUMN estado ENUM('pendiente','en_revision','aprobada','rechazada','apelado') DEFAULT 'pendiente'");
    }
};
