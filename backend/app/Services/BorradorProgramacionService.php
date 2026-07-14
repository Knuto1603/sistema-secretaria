<?php

namespace App\Services;

use App\Imports\BorradorMatrizImport;
use App\Models\BorradorProgramacion;
use App\Models\BorradorSeccion;
use App\Models\Escuela;
use App\Models\ModificacionProgramacion;
use App\Models\Plan;
use App\Models\ProgramacionSeccion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class BorradorProgramacionService
{
    public function listar(string $periodoId): Collection
    {
        return BorradorProgramacion::with(['periodo', 'creadoPor'])
            ->where('periodo_id', $periodoId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function obtener(string $id): BorradorProgramacion
    {
        return BorradorProgramacion::with([
            'periodo',
            'creadoPor',
            'publicadoPor',
            'secciones.curso',
            'secciones.escuela',
            'secciones.docente',
            'secciones.aulaRelacion.pabellon',
            'secciones.grupoHorario.detalles',
        ])->findOrFail($id);
    }

    public function generar(
        string $periodoId,
        string $cicloTipo,
        string $nombre,
        User $creadoPor
    ): BorradorProgramacion {
        $ciclos = $cicloTipo === 'par'
            ? [2, 4, 6, 8, 10]
            : [1, 3, 5, 7, 9];

        return DB::transaction(function () use ($periodoId, $cicloTipo, $nombre, $creadoPor, $ciclos) {
            $borrador = BorradorProgramacion::create([
                'periodo_id' => $periodoId,
                'nombre'     => $nombre,
                'ciclo_tipo' => $cicloTipo,
                'estado'     => 'borrador',
                'creado_por' => $creadoPor->id,
            ]);

            $escuelas = Escuela::all();
            $secciones = [];

            foreach ($escuelas as $escuela) {
                $plan = Plan::where('escuela_id', $escuela->id)
                    ->where('activo', true)
                    ->first();

                if (!$plan) {
                    continue;
                }

                $cursosDePlan = $plan->cursos()
                    ->with('curso')
                    ->where('tipo', 'O')
                    ->whereIn('ciclo', $ciclos)
                    ->orderBy('ciclo')
                    ->get();

                foreach ($cursosDePlan as $cursoPlan) {
                    $base = [
                        'programacion_id'     => $borrador->id,
                        'curso_id'            => $cursoPlan->curso_id,
                        'escuela_programada_id' => $escuela->id,
                        'ciclo'               => $cursoPlan->ciclo,
                        'tipo'                => 'O',
                        'docente_id'          => null,
                        'aula_id'             => null,
                        'grupo_horario_id'    => null,
                        'capacidad'           => 30,
                        'n_inscritos'         => 0,
                        'lleno_manual'        => false,
                        'activo'              => true,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ];

                    $secciones[] = array_merge($base, ['id' => (string) \Illuminate\Support\Str::uuid(), 'seccion' => '1']);
                    $secciones[] = array_merge($base, ['id' => (string) \Illuminate\Support\Str::uuid(), 'seccion' => '2']);
                }
            }

            if (!empty($secciones)) {
                BorradorSeccion::insert($secciones);
            }

            return $this->obtener($borrador->id);
        });
    }

    public function importarMatriz(
        string $periodoId,
        string $cicloTipo,
        string $nombre,
        User $creadoPor,
        UploadedFile $file
    ): array {
        return DB::transaction(function () use ($periodoId, $cicloTipo, $nombre, $creadoPor, $file) {
            $borrador = BorradorProgramacion::create([
                'periodo_id' => $periodoId,
                'nombre'     => $nombre,
                'ciclo_tipo' => $cicloTipo,
                'estado'     => 'borrador',
                'creado_por' => $creadoPor->id,
            ]);

            $importer = new BorradorMatrizImport($borrador->id);
            Excel::import($importer, $file);

            return [
                'borrador' => $this->obtener($borrador->id),
                'resumen'  => $importer->getResumen(),
            ];
        });
    }

    public function agregarSeccion(BorradorProgramacion $borrador, array $data): BorradorSeccion
    {
        $this->verificarEditable($borrador);

        $total = BorradorSeccion::where('programacion_id', $borrador->id)
            ->where('curso_id', $data['curso_id'])
            ->where('escuela_programada_id', $data['escuela_id'] ?? $data['escuela_programada_id'])
            ->count();

        $data['seccion']         = (string) ($total + 1);
        $data['programacion_id'] = $borrador->id;

        // Normalizar campo escuela
        if (isset($data['escuela_id']) && !isset($data['escuela_programada_id'])) {
            $data['escuela_programada_id'] = $data['escuela_id'];
            unset($data['escuela_id']);
        }

        $seccion = BorradorSeccion::create($data);

        return $seccion->load(['curso', 'escuela', 'docente', 'aula.pabellon', 'grupoHorario.detalles']);
    }

    public function actualizarSeccion(BorradorProgramacion $borrador, string $seccionId, array $data): BorradorSeccion
    {
        $this->verificarEditable($borrador);

        $seccion = BorradorSeccion::where('programacion_id', $borrador->id)
            ->findOrFail($seccionId);

        $campos = ['aula_id', 'grupo_horario_id', 'docente_id', 'capacidad'];
        $seccion->update(array_intersect_key($data, array_flip($campos)));

        return $seccion->load(['curso', 'escuela', 'docente', 'aula.pabellon', 'grupoHorario.detalles']);
    }

    public function bulkActualizar(BorradorProgramacion $borrador, array $cambios): void
    {
        $this->verificarEditable($borrador);

        DB::transaction(function () use ($borrador, $cambios) {
            foreach ($cambios as $cambio) {
                BorradorSeccion::where('programacion_id', $borrador->id)
                    ->where('id', $cambio['id'])
                    ->update([
                        'aula_id'          => $cambio['aula_id'] ?? null,
                        'grupo_horario_id' => $cambio['grupo_horario_id'] ?? null,
                        'updated_at'       => now(),
                    ]);
            }
        });
    }

    public function eliminarSeccion(BorradorProgramacion $borrador, string $seccionId): void
    {
        $this->verificarEditable($borrador);

        BorradorSeccion::where('programacion_id', $borrador->id)
            ->findOrFail($seccionId)
            ->delete();
    }

    /**
     * Publica el borrador: cambia estado a 'publicado'.
     * En el nuevo modelo, las secciones ya existen en programacion_secciones;
     * solo se cambia el estado y se asocian las escuelas habilitadas.
     */
    public function publicar(BorradorProgramacion $borrador, User $publicadoPor): BorradorProgramacion
    {
        $this->verificarEditable($borrador);

        $yaPublicado = BorradorProgramacion::where('periodo_id', $borrador->periodo_id)
            ->where('estado', 'publicado')
            ->where('id', '!=', $borrador->id)
            ->exists();

        if ($yaPublicado) {
            throw new \RuntimeException('Ya existe una programación publicada para este periodo. Solo puede haber una programación activa por periodo.');
        }

        return DB::transaction(function () use ($borrador, $publicadoPor) {
            // Asociar escuela_programada como escuela habilitada en cada sección
            foreach ($borrador->secciones()->get() as $seccion) {
                if ($seccion->escuela_programada_id) {
                    $seccion->escuelas()->syncWithoutDetaching([$seccion->escuela_programada_id]);
                }
            }

            $borrador->update([
                'estado'        => 'publicado',
                'publicado_por' => $publicadoPor->id,
                'publicado_at'  => now(),
            ]);

            return $borrador->fresh(['periodo', 'creadoPor', 'publicadoPor']);
        });
    }

    /**
     * Revierte un borrador publicado a estado 'borrador'.
     * En el nuevo modelo solo se cambia el estado — las secciones permanecen
     * pero se resetean los campos operativos (n_inscritos, lleno_manual).
     */
    public function revertir(BorradorProgramacion $borrador): BorradorProgramacion
    {
        if ($borrador->esBorrador()) {
            throw new Exception('Este borrador ya está en estado borrador.');
        }

        return DB::transaction(function () use ($borrador) {
            $ids = $borrador->secciones()->pluck('id');

            // Desligar modificaciones
            ModificacionProgramacion::where('borrador_id', $borrador->id)
                ->update(['programacion_id' => null]);

            // Limpiar datos operativos de inscripciones y solicitudes
            if ($ids->isNotEmpty()) {
                DB::table('solicitud')->whereIn('programacion_id', $ids)->delete();
                DB::table('inscripciones')->whereIn('programacion_id', $ids)->delete();
                DB::table('programacion_escuelas')->whereIn('programacion_id', $ids)->delete();
            }

            // Resetear campos operativos de las secciones
            $borrador->secciones()->update([
                'n_inscritos'  => 0,
                'lleno_manual' => false,
                'activo'       => true,
            ]);

            $borrador->update([
                'estado'        => 'borrador',
                'publicado_por' => null,
                'publicado_at'  => null,
            ]);

            return $borrador->fresh(['periodo', 'creadoPor', 'publicadoPor']);
        });
    }

    /**
     * Elimina un borrador completo junto con todas sus secciones (cascade).
     */
    public function eliminar(BorradorProgramacion $borrador): void
    {
        DB::transaction(function () use ($borrador) {
            if (!$borrador->esBorrador()) {
                $ids = $borrador->secciones()->pluck('id');

                if ($ids->isNotEmpty()) {
                    ModificacionProgramacion::where('borrador_id', $borrador->id)
                        ->update(['programacion_id' => null]);

                    DB::table('solicitud')->whereIn('programacion_id', $ids)->delete();
                    DB::table('inscripciones')->whereIn('programacion_id', $ids)->delete();
                    DB::table('programacion_escuelas')->whereIn('programacion_id', $ids)->delete();
                }
            }

            $borrador->delete(); // cascade elimina programacion_secciones y modificaciones
        });
    }

    public function autoAsignar(BorradorProgramacion $borrador): array
    {
        $this->verificarEditable($borrador);

        return DB::transaction(function () use ($borrador) {
            $secciones = BorradorSeccion::where('programacion_id', $borrador->id)
                ->with(['escuela', 'curso'])
                ->get();

            if ($secciones->isEmpty()) {
                return ['total' => 0, 'asignadas' => 0, 'sin_asignar' => 0];
            }

            $asignador    = new AutoAsignadorProgramacion();
            $asignaciones = $asignador->distribuir($secciones);

            if (!empty($asignaciones)) {
                $this->persistirAsignaciones($secciones, $asignaciones);
            }

            $total    = $secciones->count();
            $asignadas = count($asignaciones);

            return [
                'total'       => $total,
                'asignadas'   => $asignadas,
                'sin_asignar' => $total - $asignadas,
                'aulas'       => $asignador->resumenAulas(),
            ];
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function verificarEditable(BorradorProgramacion $borrador): void
    {
        if (!$borrador->esBorrador()) {
            throw new Exception('Este borrador ya fue publicado y no puede modificarse.');
        }
    }

    private function persistirAsignaciones(Collection $secciones, array $asignaciones): void
    {
        $seccionesMap = $secciones->keyBy('id');
        $now = now();

        $filas = array_map(function (string $seccionId, array $datos) use ($seccionesMap, $now) {
            $s = $seccionesMap[$seccionId];
            return [
                'id'               => $s->id,
                'programacion_id'  => $s->programacion_id,
                'curso_id'         => $s->curso_id,
                'escuela_programada_id' => $s->escuela_programada_id,
                'ciclo'            => $s->ciclo,
                'tipo'             => $s->tipo,
                'seccion'          => $s->seccion,
                'docente_id'       => $s->docente_id,
                'capacidad'        => $s->capacidad,
                'aula_id'          => $datos['aula_id'],
                'grupo_horario_id' => $datos['grupo_horario_id'],
                'created_at'       => $s->created_at,
                'updated_at'       => $now,
            ];
        }, array_keys($asignaciones), array_values($asignaciones));

        BorradorSeccion::upsert(
            $filas,
            ['id'],
            ['aula_id', 'grupo_horario_id', 'updated_at']
        );
    }
}
