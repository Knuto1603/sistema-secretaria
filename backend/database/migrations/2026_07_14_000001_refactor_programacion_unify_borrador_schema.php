<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor mayor: unifica borradores_programacion + programacion_academica
 * en un único modelo de ciclo de vida.
 *
 * borradores_programacion  →  programaciones          (maestro, estados: borrador | publicado)
 * borradores_secciones  ┐
 * programacion_academica ┘  →  programacion_secciones (detalle, siempre ligado a su programacion)
 *
 * Estrategia de migración de datos:
 * - Borradores publicados: se migran desde programacion_academica (conservando su id
 *   para no romper FKs de inscripciones, solicitudes, programacion_escuelas, modificaciones).
 * - Borradores en estado borrador: se migran desde borradores_secciones.
 * - borradores_secciones de borradores publicados se descartan (supersedidos por programacion_academica).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Liberar FKs que apuntan a borradores_programacion ───────────
        Schema::table('borradores_secciones', function (Blueprint $table) {
            $table->dropForeign(['borrador_id']);
        });
        Schema::table('modificaciones_programacion', function (Blueprint $table) {
            $table->dropForeign(['borrador_id']);
        });

        // ── 2. Renombrar borradores_programacion → programaciones ───────────
        Schema::rename('borradores_programacion', 'programaciones');

        // ── 3. Re-agregar FKs apuntando al nuevo nombre ─────────────────────
        Schema::table('borradores_secciones', function (Blueprint $table) {
            $table->foreign('borrador_id')
                ->references('id')->on('programaciones')
                ->cascadeOnDelete();
        });
        Schema::table('modificaciones_programacion', function (Blueprint $table) {
            $table->foreign('borrador_id')
                ->references('id')->on('programaciones')
                ->cascadeOnDelete();
        });

        // ── 4. Crear programacion_secciones ────────────────────────────────
        Schema::create('programacion_secciones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('programacion_id')
                ->constrained('programaciones')
                ->cascadeOnDelete();
            $table->foreignUuid('curso_id')->constrained('cursos');
            $table->foreignUuid('escuela_programada_id')
                ->nullable()->constrained('escuelas')->nullOnDelete();
            $table->tinyInteger('ciclo')->unsigned()->nullable();
            $table->char('tipo', 1)->default('O');     // O = Obligatorio, E = Electivo
            $table->string('seccion', 10)->nullable();
            $table->foreignUuid('docente_id')
                ->nullable()->constrained('docentes')->nullOnDelete();
            $table->foreignUuid('aula_id')
                ->nullable()->constrained('aulas')->nullOnDelete();
            $table->foreignUuid('grupo_horario_id')
                ->nullable()->constrained('grupos_horario')->nullOnDelete();
            $table->unsignedSmallInteger('capacidad')->default(30);
            // Campos operativos (relevantes cuando la programacion está publicada)
            $table->integer('n_inscritos')->default(0);
            $table->boolean('lleno_manual')->default(false);
            $table->boolean('activo')->default(true);
            // Campos legacy (vienen del importador Excel / SIGA)
            $table->string('clave')->nullable();
            $table->string('grupo')->nullable();
            $table->string('aula')->nullable();
            $table->string('n_acta')->nullable();
            $table->timestamps();
        });

        // ── 5. Migrar programacion_academica → programacion_secciones ──────
        //    Solo para borradores publicados; conserva los mismos IDs para que
        //    inscripciones, solicitudes y modificaciones sigan siendo válidos.
        DB::statement("
            INSERT INTO programacion_secciones (
                id, programacion_id, curso_id, escuela_programada_id,
                ciclo, tipo, seccion, docente_id, aula_id, grupo_horario_id,
                capacidad, n_inscritos, lleno_manual, activo,
                clave, grupo, aula, n_acta,
                created_at, updated_at
            )
            SELECT
                pa.id,
                p.id,
                pa.curso_id,
                pa.escuela_programada_id,
                pa.ciclo,
                'O',
                pa.seccion,
                pa.docente_id,
                pa.aula_id,
                pa.grupo_horario_id,
                pa.capacidad,
                pa.n_inscritos,
                pa.lleno_manual,
                COALESCE(pa.activo, 1),
                pa.clave,
                pa.grupo,
                pa.aula,
                pa.n_acta,
                pa.created_at,
                pa.updated_at
            FROM programacion_academica pa
            INNER JOIN programaciones p
                ON p.periodo_id = pa.periodo_id AND p.estado = 'publicado'
        ");

        // ── 6. Migrar borradores_secciones → programacion_secciones ─────────
        //    Solo para borradores en estado 'borrador' (los publicados
        //    ya están cubiertos arriba vía programacion_academica).
        DB::statement("
            INSERT INTO programacion_secciones (
                id, programacion_id, curso_id, escuela_programada_id,
                ciclo, tipo, seccion, docente_id, aula_id, grupo_horario_id,
                capacidad, n_inscritos, lleno_manual, activo,
                clave, grupo, aula, n_acta,
                created_at, updated_at
            )
            SELECT
                bs.id,
                bs.borrador_id,
                bs.curso_id,
                bs.escuela_id,
                bs.ciclo,
                bs.tipo,
                bs.seccion,
                bs.docente_id,
                bs.aula_id,
                bs.grupo_horario_id,
                bs.capacidad,
                0, 0, 1,
                NULL, NULL, NULL, NULL,
                bs.created_at,
                bs.updated_at
            FROM borradores_secciones bs
            INNER JOIN programaciones p ON p.id = bs.borrador_id AND p.estado = 'borrador'
        ");

        // ── 7. Redirigir FKs externos de programacion_academica → programacion_secciones
        Schema::table('programacion_escuelas', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->cascadeOnDelete();
        });
        Schema::table('modificaciones_programacion', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->nullOnDelete();
        });
        Schema::table('solicitud', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->nullOnDelete();
        });
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->cascadeOnDelete();
        });

        // ── 8. Eliminar tablas obsoletas ────────────────────────────────────
        Schema::dropIfExists('borradores_secciones');
        Schema::dropIfExists('programacion_academica');
    }

    public function down(): void
    {
        throw new \RuntimeException('Esta migración no es reversible automáticamente.');
    }
};
