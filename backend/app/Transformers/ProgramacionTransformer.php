<?php

namespace App\Transformers;

use App\DTOs\Programacion\ProgramacionResponseDTO;
use App\Models\Aula;
use App\Models\ProgramacionAcademica;
use Illuminate\Support\Collection;

class ProgramacionTransformer
{
    public function toDTO(ProgramacionAcademica $model): ProgramacionResponseDTO
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

        return new ProgramacionResponseDTO(
            id:               $model->id,
            curso_id:         $model->curso_id,
            periodo_id:       $model->periodo_id,
            docente_id:       $model->docente_id,
            grupo_horario_id: $model->grupo_horario_id,
            aula_id:          $model->aula_id,
            clave:            $model->clave,
            grupo:            $grupoNombre,
            seccion:          $model->seccion,
            aula:             $model->aula,
            aula_nombre:      $aulaNombre,
            n_acta:           $model->n_acta,
            capacidad:        $model->capacidad,
            n_inscritos:      $model->n_inscritos,
            lleno_manual:     (bool) $model->lleno_manual,
            esta_lleno:       $model->estaLleno(),
            curso: $model->curso ? [
                'id'     => $model->curso->id,
                'codigo' => $model->curso->codigo,
                'nombre' => $model->curso->nombre,
            ] : null,
            periodo: $model->periodo ? [
                'id'     => $model->periodo->id,
                'nombre' => $model->periodo->nombre,
                'activo' => (bool) $model->periodo->activo,
            ] : null,
            docente: $model->docente ? [
                'id'             => $model->docente->id,
                'nombre_completo'=> $model->docente->nombre_completo,
            ] : null,
            aula_rel:      $aulaRel,
            grupo_horario: $grupoHorario,
            escuelas:           $escuelas,
            escuela_programada: $escuelaProgramada,
            created_at:         $model->created_at->toISOString()
        );
    }

    public function toArray(ProgramacionAcademica $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
