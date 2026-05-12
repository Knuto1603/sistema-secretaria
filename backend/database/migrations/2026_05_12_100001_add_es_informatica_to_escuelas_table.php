<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->boolean('es_informatica')->default(false)->after('nombre_corto');
        });

        // Auto-detección inicial: marcar escuelas cuyo nombre contenga "Informática"
        // IMPORTANTE: verificar y ajustar manualmente si es necesario.
        DB::table('escuelas')
            ->whereRaw("LOWER(nombre) LIKE '%inform%'")
            ->update(['es_informatica' => true]);
    }

    public function down(): void
    {
        Schema::table('escuelas', function (Blueprint $table) {
            $table->dropColumn('es_informatica');
        });
    }
};
