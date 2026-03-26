<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normaliza los nombres de grupo importados desde Campus UNP.
 *
 * Campus envía grupos con sufijos de turno/letra como G1A, G1AB, G1AH, G10AB, G3AH, etc.
 * Todos hacen referencia al grupo base (G1, G10, G3, ...).
 *
 * Esta migración:
 *   1. Busca registros en grupos_horario con nombre G{n}{sufijo}.
 *   2. Si existe el grupo base G{n}, redirige los FK de programacion_academica al base y elimina el duplicado.
 *   3. Si el grupo base no existe, renombra el registro al nombre base.
 *   4. Normaliza también la columna texto `grupo` en programacion_academica.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Normalizar grupos_horario ──────────────────────────────────────
        $gruposConSufijo = DB::table('grupos_horario')
            ->get(['id', 'nombre'])
            ->filter(fn($g) => preg_match('/^G\d+[A-Z]+$/i', trim($g->nombre)));

        foreach ($gruposConSufijo as $grupo) {
            $nombreBase = $this->extraerBase($grupo->nombre);

            if ($nombreBase === strtoupper(trim($grupo->nombre))) {
                // No tiene sufijo real, saltar
                continue;
            }

            $grupoBase = DB::table('grupos_horario')
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [$nombreBase])
                ->first();

            if ($grupoBase) {
                // Redirigir todas las programaciones a la base y eliminar el duplicado
                DB::table('programacion_academica')
                    ->where('grupo_horario_id', $grupo->id)
                    ->update(['grupo_horario_id' => $grupoBase->id]);

                DB::table('grupos_horario')->where('id', $grupo->id)->delete();
            } else {
                // No existe la base → renombrar este registro al nombre base
                DB::table('grupos_horario')
                    ->where('id', $grupo->id)
                    ->update(['nombre' => $nombreBase]);
            }
        }

        // ── 2. Normalizar columna texto `grupo` en programacion_academica ────
        $rows = DB::table('programacion_academica')
            ->whereNotNull('grupo')
            ->whereRaw("grupo REGEXP '^G[0-9]+[A-Za-z]+'")
            ->get(['id', 'grupo']);

        foreach ($rows as $row) {
            $base = $this->extraerBase($row->grupo);
            if ($base !== strtoupper(trim($row->grupo))) {
                DB::table('programacion_academica')
                    ->where('id', $row->id)
                    ->update(['grupo' => $base]);
            }
        }
    }

    public function down(): void
    {
        // No reversible: los sufijos originales se habrían perdido.
    }

    /**
     * Extrae el nombre base de un grupo con sufijo.
     * G1A -> G1, G10AB -> G10, G3AH -> G3, G5BH -> G5
     */
    private function extraerBase(string $nombre): string
    {
        $nombre = strtoupper(trim($nombre));
        if (preg_match('/^(G\d+)[A-Z]+$/', $nombre, $m)) {
            return $m[1];
        }
        return $nombre;
    }
};
