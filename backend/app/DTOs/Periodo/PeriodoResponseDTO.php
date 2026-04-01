<?php

namespace App\DTOs\Periodo;

readonly class PeriodoResponseDTO
{
    public function __construct(
        public string $id,
        public string $nombre,
        public ?string $fecha_inicio,
        public ?string $fecha_fin,
        public bool $activo,
        public bool $solicitudes_abiertas,
        public string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'activo' => $this->activo,
            'solicitudes_abiertas' => $this->solicitudes_abiertas,
            'created_at' => $this->created_at,
        ];
    }
}
