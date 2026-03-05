<?php

namespace App\DTOs\Curso;

readonly class CursoResponseDTO
{
    public function __construct(
        public string $id,
        public string $codigo,
        public string $nombre,
        public ?string $area_id,
        public ?array $area,
        public string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'area_id' => $this->area_id,
            'area' => $this->area,
            'created_at' => $this->created_at,
        ];
    }
}
