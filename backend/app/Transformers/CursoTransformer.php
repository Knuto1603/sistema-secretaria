<?php

namespace App\Transformers;

use App\DTOs\Curso\CursoResponseDTO;
use App\Models\Curso;
use Illuminate\Support\Collection;

class CursoTransformer
{
    public function toDTO(Curso $model): CursoResponseDTO
    {
        return new CursoResponseDTO(
            id: $model->id,
            codigo: $model->codigo,
            nombre: $model->nombre,
            area_id: $model->area_id,
            area: $model->area ? [
                'id' => $model->area->id,
                'nombre' => $model->area->nombre,
            ] : null,
            created_at: $model->created_at->toISOString()
        );
    }

    public function toArray(Curso $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
