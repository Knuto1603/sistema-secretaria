<?php

namespace App\Transformers;

use App\DTOs\Periodo\PeriodoResponseDTO;
use App\Models\Periodo;
use Illuminate\Support\Collection;

class PeriodoTransformer
{
    public function toDTO(Periodo $model): PeriodoResponseDTO
    {
        return new PeriodoResponseDTO(
            id: $model->id,
            nombre: $model->nombre,
            fecha_inicio: $model->fecha_inicio?->format('Y-m-d'),
            fecha_fin: $model->fecha_fin?->format('Y-m-d'),
            activo: (bool) $model->activo,
            created_at: $model->created_at->toISOString()
        );
    }

    public function toArray(Periodo $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
