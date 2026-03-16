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
        // --- Columnas nuevas (solo si no existen) ---
        Schema::table('plan_estudios', function (Blueprint $table) {
            if (!Schema::hasColumn('plan_estudios', 'plan_id')) {
                $table->uuid('plan_id')->nullable()->after('escuela_id');
            }
            if (!Schema::hasColumn('plan_estudios', 'horas_teoricas')) {
                $table->tinyInteger('horas_teoricas')->unsigned()->nullable()->after('creditos');
            }
            if (!Schema::hasColumn('plan_estudios', 'horas_practicas')) {
                $table->tinyInteger('horas_practicas')->unsigned()->nullable()->after('horas_teoricas');
            }
        });

        // --- FK plan_id → planes_estudios (solo si no existe) ---
        $fksPlanEstudios = $this->getForeignKeys('plan_estudios');
        if (!in_array('plan_estudios_plan_id_foreign', $fksPlanEstudios)) {
            Schema::table('plan_estudios', function (Blueprint $table) {
                $table->foreign('plan_id')->references('id')->on('planes_estudios')->nullOnDelete();
            });
        }

        // --- Crear "Plan inicial" por escuela y asignar plan_id (solo filas sin plan_id) ---
        $escuelasConRegistros = DB::table('plan_estudios')
            ->select('escuela_id')
            ->distinct()
            ->whereNotNull('escuela_id')
            ->whereNull('plan_id')
            ->get();

        foreach ($escuelasConRegistros as $row) {
            // Buscar si ya existe un plan activo para esa escuela
            $planExistente = DB::table('planes_estudios')
                ->where('escuela_id', $row->escuela_id)
                ->where('activo', true)
                ->value('id');

            $planId = $planExistente ?? Str::uuid()->toString();

            if (!$planExistente) {
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
            }

            DB::table('plan_estudios')
                ->where('escuela_id', $row->escuela_id)
                ->whereNull('plan_id')
                ->update(['plan_id' => $planId]);
        }

        // --- Reemplazar unique (escuela_id, curso_id) → (plan_id, curso_id) ---
        $indexes = $this->getIndexes('plan_estudios');

        // Soltar FK y unique viejos solo si existen
        $fks = $this->getForeignKeys('plan_estudios');
        Schema::table('plan_estudios', function (Blueprint $table) use ($indexes, $fks) {
            if (in_array('plan_estudios_escuela_id_foreign', $fks)) {
                $table->dropForeign(['escuela_id']);
            }
            if (in_array('plan_estudios_escuela_id_curso_id_unique', $indexes)) {
                $table->dropUnique(['escuela_id', 'curso_id']);
            }
        });

        // Recrear FK escuela_id si ya no existe
        $fksActualizados = $this->getForeignKeys('plan_estudios');
        if (!in_array('plan_estudios_escuela_id_foreign', $fksActualizados)) {
            Schema::table('plan_estudios', function (Blueprint $table) {
                $table->foreign('escuela_id')->references('id')->on('escuelas')->cascadeOnDelete();
            });
        }

        // Crear nuevo unique (plan_id, curso_id) si no existe
        $indexesActualizados = $this->getIndexes('plan_estudios');
        if (!in_array('plan_estudios_plan_id_curso_id_unique', $indexesActualizados)) {
            Schema::table('plan_estudios', function (Blueprint $table) {
                $table->unique(['plan_id', 'curso_id']);
            });
        }
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

    private function getForeignKeys(string $table): array
    {
        return collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$table]))->pluck('CONSTRAINT_NAME')->toArray();
    }

    private function getIndexes(string $table): array
    {
        return collect(DB::select("
            SELECT DISTINCT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ", [$table]))->pluck('INDEX_NAME')->toArray();
    }
};
