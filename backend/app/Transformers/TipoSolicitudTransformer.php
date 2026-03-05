<?php

namespace App\Transformers;

use App\DTOs\TipoSolicitud\TipoSolicitudResponseDTO;
use App\Models\TipoSolicitud;
use Illuminate\Support\Collection;

class TipoSolicitudTransformer
{
    public function toDTO(TipoSolicitud $model): TipoSolicitudResponseDTO
    {
        return new TipoSolicitudResponseDTO(
            id: $model->id,
            codigo: $model->codigo,
            nombre: $model->nombre,
            descripcion: $model->descripcion,
            requiere_archivo: $model->requiere_archivo,
            activo: $model->activo,
            created_at: $model->created_at->toISOString()
        );
    }

    public function toArray(TipoSolicitud $model): array
    {
        return $this->toDTO($model)->toArray();
    }

    public function collection(Collection $models): array
    {
        return $models->map(fn($m) => $this->toArray($m))->toArray();
    }
}
