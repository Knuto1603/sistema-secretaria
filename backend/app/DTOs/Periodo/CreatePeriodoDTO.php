<?php

namespace App\DTOs\Periodo;

readonly class CreatePeriodoDTO
{
    public function __construct(
        public string $nombre,
        public ?string $fecha_inicio = null,
        public ?string $fecha_fin = null,
        public bool $activo = false
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nombre: $data['nombre'],
            fecha_inicio: $data['fecha_inicio'] ?? null,
            fecha_fin: $data['fecha_fin'] ?? null,
            activo: $data['activo'] ?? false
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'nombre' => $this->nombre,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'activo' => $this->activo,
        ], fn($v) => $v !== null);
    }
}
