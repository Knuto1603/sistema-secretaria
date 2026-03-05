<?php

namespace App\DTOs\Curso;

readonly class CreateCursoDTO
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $area_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            codigo: $data['codigo'],
            nombre: $data['nombre'],
            area_id: $data['area_id'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'area_id' => $this->area_id,
        ], fn($v) => $v !== null);
    }
}
