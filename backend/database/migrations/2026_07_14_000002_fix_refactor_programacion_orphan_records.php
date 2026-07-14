<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Continuación de la migración 2026_07_14_000001 que falló a mitad.
 *
 * Estado actual de la BD tras el fallo:
 * - programaciones         existe (renombrada de borradores_programacion)
 * - programacion_secciones existe con datos de borradores publicados y borradores en borrador
 * - programacion_academica TODAVÍA existe (el DROP no llegó a ejecutarse)
 * - borradores_secciones   TODAVÍA existe
 * - programacion_escuelas  sin FK (la antigua fue dropeada, la nueva falló al agregar)
 * - modificaciones_programacion, solicitud, inscripciones: FKs aún apuntan a programacion_academica
 *
 * Causa del fallo: registros en programacion_academica sin borrador publicado para su período
 * (creados por importar-diff o import directo). No fueron migrados en el paso 5.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Para períodos sin programacion publicada, crear una ──────────
        //    (necesario para poder migrar los registros huérfanos)
        $primerUsuario = DB::table('users')->orderBy('created_at')->value('id');

        $periodosHuerfanos = DB::select("
            SELECT DISTINCT pa.periodo_id, per.nombre
            FROM programacion_academica pa
            JOIN periodos per ON per.id = pa.periodo_id
            WHERE pa.id NOT IN (SELECT id FROM programacion_secciones)
              AND NOT EXISTS (
                SELECT 1 FROM programaciones p
                WHERE p.periodo_id = pa.periodo_id AND p.estado = 'publicado'
              )
        ");

        foreach ($periodosHuerfanos as $row) {
            DB::table('programaciones')->insert([
                'id'          => (string) \Illuminate\Support\Str::uuid(),
                'periodo_id'  => $row->periodo_id,
                'nombre'      => 'Importado ' . $row->nombre,
                'ciclo_tipo'  => 'impar',
                'estado'      => 'publicado',
                'creado_por'  => $primerUsuario,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // ── 2. Migrar registros huérfanos de programacion_academica ─────────
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
            WHERE pa.id NOT IN (SELECT id FROM programacion_secciones)
        ");

        // ── 3. FK programacion_escuelas → programacion_secciones ────────────
        //    (la antigua ya fue dropeada en la migración anterior; solo agregar la nueva)
        Schema::table('programacion_escuelas', function (Blueprint $table) {
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->cascadeOnDelete();
        });

        // ── 4. FK modificaciones_programacion.programacion_id ───────────────
        Schema::table('modificaciones_programacion', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->nullOnDelete();
        });

        // ── 5. FK solicitud.programacion_id ────────────────────────────────
        Schema::table('solicitud', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->nullOnDelete();
        });

        // ── 6. FK inscripciones.programacion_id ────────────────────────────
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropForeign(['programacion_id']);
            $table->foreign('programacion_id')
                ->references('id')->on('programacion_secciones')
                ->cascadeOnDelete();
        });

        // ── 7. Eliminar tablas obsoletas ────────────────────────────────────
        Schema::dropIfExists('borradores_secciones');
        Schema::dropIfExists('programacion_academica');
    }

    public function down(): void
    {
        throw new \RuntimeException('Esta migración no es reversible automáticamente.');
    }
};
