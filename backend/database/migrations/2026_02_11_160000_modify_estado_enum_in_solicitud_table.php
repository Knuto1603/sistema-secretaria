<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modificar el ENUM para que coincida con los valores usados en el código
        DB::statement("ALTER TABLE solicitud MODIFY COLUMN estado ENUM('pendiente', 'en_revision', 'aprobada', 'rechazada') DEFAULT 'pendiente'");

        // Actualizar registros existentes que tengan valores antiguos
        DB::table('solicitud')->where('estado', 'revisado')->update(['estado' => 'en_revision']);
        DB::table('solicitud')->where('estado', 'validado')->update(['estado' => 'en_revision']);
        DB::table('solicitud')->where('estado', 'aprobado')->update(['estado' => 'aprobada']);
        DB::table('solicitud')->where('estado', 'rechazado')->update(['estado' => 'rechazada']);
    }

    public function down(): void
    {
        // Revertir al ENUM original
        DB::statement("ALTER TABLE solicitud MODIFY COLUMN estado ENUM('pendiente', 'revisado', 'validado', 'aprobado', 'rechazado') DEFAULT 'pendiente'");

        // Revertir los valores
        DB::table('solicitud')->where('estado', 'en_revision')->update(['estado' => 'revisado']);
        DB::table('solicitud')->where('estado', 'aprobada')->update(['estado' => 'aprobado']);
        DB::table('solicitud')->where('estado', 'rechazada')->update(['estado' => 'rechazado']);
    }
};
