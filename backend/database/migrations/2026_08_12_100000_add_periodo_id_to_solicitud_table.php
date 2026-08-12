<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitud', function (Blueprint $table) {
            $table->foreignUuid('periodo_id')->nullable()->after('programacion_id')
                ->constrained('periodos')->restrictOnDelete();
        });

        // Backfill: resolver el periodo de cada solicitud existente via la cadena
        // programacion_secciones -> programaciones -> periodo_id (misma cadena que
        // SolicitudService::create() usa desde ahora para guardarlo directamente).
        // Se hace con query builder (no JOIN crudo en el UPDATE) para que funcione
        // igual en mysql (produccion) y sqlite (tests, phpunit.xml usa :memory:).
        DB::table('solicitud')
            ->whereNotNull('programacion_id')
            ->orderBy('id')
            ->chunkById(500, function ($solicitudes) {
                $programacionIds = $solicitudes->pluck('programacion_id')->unique()->values();

                $periodoPorProgramacion = DB::table('programacion_secciones')
                    ->join('programaciones', 'programaciones.id', '=', 'programacion_secciones.programacion_id')
                    ->whereIn('programacion_secciones.id', $programacionIds)
                    ->pluck('programaciones.periodo_id', 'programacion_secciones.id');

                foreach ($solicitudes as $solicitud) {
                    $periodoId = $periodoPorProgramacion[$solicitud->programacion_id] ?? null;

                    if ($periodoId) {
                        DB::table('solicitud')
                            ->where('id', $solicitud->id)
                            ->update(['periodo_id' => $periodoId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('solicitud', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periodo_id');
        });
    }
};
