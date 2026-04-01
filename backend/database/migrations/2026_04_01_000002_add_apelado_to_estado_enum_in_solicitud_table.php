<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE solicitud MODIFY COLUMN estado ENUM('pendiente','en_revision','aprobada','rechazada','apelado') DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        // Revertir apelados a en_revision antes de quitar el valor del ENUM
        DB::table('solicitud')->where('estado', 'apelado')->update(['estado' => 'en_revision']);
        DB::statement("ALTER TABLE solicitud MODIFY COLUMN estado ENUM('pendiente','en_revision','aprobada','rechazada') DEFAULT 'pendiente'");
    }
};
