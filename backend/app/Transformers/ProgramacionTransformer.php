<?php

namespace App\Transformers;

use App\DTOs\Programacion\ProgramacionResponseDTO;
use App\Models\ProgramacionAcademica;
use Illuminate\Support\Collection;

class ProgramacionTransformer
{
    public function toDTO(ProgramacionAcademica $model): ProgramacionResponseDTO
    {
        return new ProgramacionResponseDTO(
            id: $model->id,
            curso_id: $model->curso_id,
            periodo_id: $model->periodo_id,
            docente_id: $model->docente_id,
            clave: $model->clave,
            grupo: $model->grupo,
            seccion: $model->seccion,
            aula: $model->aula,
            n_acta: $model->n_acta,
            capacidad: $model->capacidad,
            n_inscritos: $model->n_inscritos,
            lleno_manual: (bool) $model->lleno_manual,
            esta_lleno: $model->estaLleno(),
            curso: $model->curso ? [
                'id' => $model->curso->id,
                'codigo' => $model->curso->codigo,
                'nombre' => $model->curso->nombre,
            ] : null,
            periodo: $model->periodo ? [
                'id' => $model->periodo->id,
                'nombre' => $model->periodo->nombre,
                'activo' => (bool) $model->periodo->activo,
            ] : null,
            docente: $model->docente ? [
                'id' => $model->docente->id,
                'nombre_completo' => $model->docente->nombre_completo,
            ] : null,
            created_at: $model->created_at->toISOString()
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
