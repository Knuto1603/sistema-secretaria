<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas nuevas
        Schema::table('plan_estudios', function (Blueprint $table) {
            $table->uuid('plan_id')->nullable()->after('escuela_id');
            $table->tinyInteger('horas_teoricas')->unsigned()->nullable()->after('creditos');
            $table->tinyInteger('horas_practicas')->unsigned()->nullable()->after('horas_teoricas');
        });

        // Crear FK para plan_id ahora que la columna existe
        Schema::table('plan_estudios', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('planes_estudios')->nullOnDelete();
        });

        // Crear un "Plan inicial" para cada escuela que tenga registros y asignar plan_id
        $escuelasConRegistros = DB::table('plan_estudios')
            ->select('escuela_id')
            ->distinct()
            ->whereNotNull('escuela_id')
            ->get();

        foreach ($escuelasConRegistros as $row) {
            $planId = Str::uuid()->toString();
            DB::table('planes_estudios')->insert([
                'id'                            => $planId,
                'nombre'                        => 'Plan inicial',
                'escuela_id'                    => $row->escuela_id,
                'activo'                        => true,
                'total_creditos_obligatorios'   => 0,
                'creditos_electivos_requeridos' => 0,
                'created_at'                    => now(),
                'updated_at'                    => now(),
            ]);

            DB::table('plan_estudios')
                ->where('escuela_id', $row->escuela_id)
                ->update(['plan_id' => $planId]);
        }

        // Reemplazar unique (escuela_id, curso_id) → (plan_id, curso_id)
        // MySQL usa el unique compuesto como índice de soporte para el FK en escuela_id,
        // por eso hay que soltar el FK primero, luego el unique, y volver a crear el FK.
        Schema::table('plan_estudios', function (Blueprint $table) {
            $table->dropForeign(['escuela_id']);
            $table->dropUnique(['escuela_id', 'curso_id']);
            // Volver a crear el FK (MySQL crea su propio índice automáticamente)
            $table->foreign('escuela_id')->references('id')->on('escuelas')->cascadeOnDelete();
            // Nuevo unique por plan
            $table->unique(['plan_id', 'curso_id']);
        });
    }

    public function down(): void
    {
        Schema::table('plan_estudios', function (Blueprint $table) {
            $table->dropForeign(['escuela_id']);
            $table->dropUnique(['plan_id', 'curso_id']);
            $table->foreign('escuela_id')->references('id')->on('escuelas')->cascadeOnDelete();
            $table->unique(['escuela_id', 'curso_id']);
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'horas_teoricas', 'horas_practicas']);
        });
    }
};
