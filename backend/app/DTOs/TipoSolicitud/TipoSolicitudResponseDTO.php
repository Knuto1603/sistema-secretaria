<?php

namespace App\DTOs\TipoSolicitud;

readonly class TipoSolicitudResponseDTO
{
    public function __construct(
        public string $id,
        public string $codigo,
        public string $nombre,
        public ?string $descripcion,
        public bool $requiere_archivo,
        public bool $activo,
        public string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'requiere_archivo' => $this->requiere_archivo,
            'activo' => $this->activo,
            'created_at' => $this->created_at,
        ];
    }
}
