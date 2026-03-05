<?php

namespace App\DTOs\Periodo;

readonly class UpdatePeriodoDTO
{
    public function __construct(
        public ?string $nombre = null,
        public ?string $fecha_inicio = null,
        public ?string $fecha_fin = null,
        public ?bool $activo = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            nombre: $data['nombre'] ?? null,
            fecha_inicio: array_key_exists('fecha_inicio', $data) ? $data['fecha_inicio'] : null,
            fecha_fin: array_key_exists('fecha_fin', $data) ? $data['fecha_fin'] : null,
            activo: isset($data['activo']) ? (bool) $data['activo'] : null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->nombre !== null) {
            $data['nombre'] = $this->nombre;
        }

        if ($this->fecha_inicio !== null) {
            $data['fecha_inicio'] = $this->fecha_inicio;
        }

        if ($this->fecha_fin !== null) {
            $data['fecha_fin'] = $this->fecha_fin;
        }

        if ($this->activo !== null) {
            $data['activo'] = $this->activo;
        }

        return $data;
    }
}
