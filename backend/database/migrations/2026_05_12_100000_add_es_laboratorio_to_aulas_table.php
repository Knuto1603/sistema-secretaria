<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->boolean('es_laboratorio')->default(false)->after('activo');
        });

        // Auto-detección por prefijo de nombre para los laboratorios existentes
        DB::table('aulas')
            ->whereRaw("UPPER(TRIM(nombre)) REGEXP '^LAB'")
            ->update(['es_laboratorio' => true]);
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropColumn('es_laboratorio');
        });
    }
};
