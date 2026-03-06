<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrige el intercambio de nombres entre Agroindustrial (código 2)
     * y Mecatrónica (código 3) que quedó mal en la migración original.
     */
    public function up(): void
    {
        DB::table('escuelas')->where('codigo', '2')->update([
            'nombre'       => 'Ingeniería Agroindustrial e Industrias Alimentarias',
            'nombre_corto' => 'Agroindustrial',
        ]);

        DB::table('escuelas')->where('codigo', '3')->update([
            'nombre'       => 'Ingeniería Mecatrónica',
            'nombre_corto' => 'Mecatrónica',
        ]);
    }

    public function down(): void
    {
        DB::table('escuelas')->where('codigo', '2')->update([
            'nombre'       => 'Ingeniería Mecatrónica',
            'nombre_corto' => 'Mecatrónica',
        ]);

        DB::table('escuelas')->where('codigo', '3')->update([
            'nombre'       => 'Ingeniería Agroindustrial e Industrias Alimentarias',
            'nombre_corto' => 'Agroindustrial',
        ]);
    }
};
