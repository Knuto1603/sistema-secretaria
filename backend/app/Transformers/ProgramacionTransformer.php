<?php

namespace App\Transformers;

use App\DTOs\Programacion\ProgramacionResponseDTO;
use App\Models\Aula;
use App\Models\ProgramacionAcademica;
use Illuminate\Support\Collection;

class ProgramacionTransformer
{
    public function toDTO(ProgramacionAcademica $model, ?string $escuelaEstudianteId = null): ProgramacionResponseDTO
    {
        // Aula desde relación aulaRelacion (sin conflicto de nombre con el campo texto 'aula')
        $aulaRel  = null;
        $aulaNombre = null;

        if ($model->relationLoaded('aulaRelacion') && $model->aulaRelacion instanceof Aula) {
            $aulaObj = $model->aulaRelacion;
            $aulaRel = [
                'id'       => $aulaObj->id,
                'nombre'   => $aulaObj->nombre,
                'capacidad'=> $aulaObj->capacidad,
                'pabellon' => $aulaObj->relationLoaded('pabellon')
                    ? ['nombre' => $aulaObj->pabellon?->nombre]
                    : null,
            ];
            // Priorizar el nombre de la tabla aulas (gestionado con pabellones)
            $aulaNombre = $aulaObj->nombre;
        }

        // Fallback al texto importado del SIGA si no hay relación
        if (!$aulaNombre) {
            $aulaNombre = $model->aula;
        }

        // Grupo horario
        $grupoHorario = null;
        $grupoNombre  = null;
        if ($model->relationLoaded('grupoHorario') && $model->grupoHorario) {
            $gh = $model->grupoHorario;
            $grupoHorario = [
                'id'      => $gh->id,
                'nombre'  => $gh->nombre,
                'detalles'=> $gh->relationLoaded('detalles')
                    ? $gh->detalles->map(fn($d) => [
                        'dia_semana'  => $d->dia_semana,
                        'hora_inicio' => $d->hora_inicio,
                        'hora_fin'    => $d->hora_fin,
                    ])->toArray()
                    : [],
            ];
            // Priorizar el nombre del catálogo de grupos sobre el texto del SIGA
            $grupoNombre = $gh->nombre;
        }

        // Fallback al texto importado del SIGA si no hay relación
        if (!$grupoNombre) {
            $grupoNombre = $model->grupo;
        }

        // Escuelas habilitadas (solo cuando se carguen explícitamente)
        $escuelas = null;
        if ($model->relationLoaded('escuelas')) {
            $escuelas = $model->escuelas->map(fn($e) => [
                'id'          => $e->id,
                'nombre'      => $e->nombre,
                'nombre_corto'=> $e->nombre_corto,
            ])->toArray();
        }

        // Escuela programada
        $escuelaProgramada = null;
        if ($model->relationLoaded('escuelaProgramada') && $model->escuelaProgramada) {
            $ep = $model->escuelaProgramada;
            $escuelaProgramada = [
                'id'          => $ep->id,
                'nombre'      => $ep->nombre,
                'nombre_corto'=> $ep->nombre_corto,
            ];
        }

        // Detectar si es equivalente: tiene relación escuelas cargada y la escuela del estudiante NO está en la lista
        $esEquivalente = false;
        if ($escuelaEstudianteId && $model->relationLoaded('escuelas')) {
            $esEquivalente = !$model->escuelas->contains('id', $escuelaEstudianteId);
        }

        // Resolver periodo via programacion->periodo (evita el accessor con query extra)
        $periodoObj = null;
        if ($model->relationLoaded('programacion') && $model->programacion) {
            $periodoObj = $model->programacion->relationLoaded('periodo')
                ? $model->programacion->periodo
                : null;
        }

        return new ProgramacionResponseDTO(
            id:               $model->id,
            docente_id:       $model->docente_id,
            grupo_horario_id: $model->grupo_horario_id,
            aula_id:          $model->aula_id,
            clave:            $model->clave,
            grupo:            $grupoNombre,
            seccion:          $model->seccion,
            aula:             $model->aula,
            aula_nombre:      $aulaNombre,
            capacidad:        $model->capacidad,
            n_inscritos:      $model->n_inscritos,
            lleno_manual:     (bool) $model->lleno_manual,
            esta_lleno:       $model->estaLleno(),
            curso: $model->curso ? [
                'id'     => $model->curso->id,
                'codigo' => $model->curso->codigo,
                'nombre' => $model->curso->nombre,
            ] : null,
            periodo: $periodoObj ? [
                'id'     => $periodoObj->id,
                'nombre' => $periodoObj->nombre,
                'activo' => (bool) $periodoObj->activo,
            ] : null,
            docente: $model->docente ? [
                'id'             => $model->docente->id,
                'nombre_completo'=> $model->docente->nombre_completo,
            ] : null,
            aula_rel:      $aulaRel,
            grupo_horario: $grupoHorario,
            escuelas:           $escuelas,
            escuela_programada: $escuelaProgramada,
            es_equivalente:     $esEquivalente,
            tipo_plan:          $model->tipo_plan ?? null,
        );
    }

    public function toArray(ProgramacionAcademica $model, ?string $escuelaEstudianteId = null): array
    {
        return $this->toDTO($model, $escuelaEstudianteId)->toArray();
    }

    public function collection(Collection $models, ?string $escuelaEstudianteId = null): array
    {
        return $models->map(fn($m) => $this->toArray($m, $escuelaEstudianteId))->toArray();
    }
}
