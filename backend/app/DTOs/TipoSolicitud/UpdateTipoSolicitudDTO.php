<?php

namespace App\DTOs\TipoSolicitud;

readonly class UpdateTipoSolicitudDTO
{
    public function __construct(
        public ?string $codigo = null,
        public ?string $nombre = null,
        public ?string $descripcion = null,
        public ?bool $requiere_archivo = null,
        public ?bool $activo = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            codigo: isset($data['codigo']) ? strtoupper($data['codigo']) : null,
            nombre: $data['nombre'] ?? null,
            descripcion: $data['descripcion'] ?? null,
            requiere_archivo: $data['requiere_archivo'] ?? null,
            activo: $data['activo'] ?? null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'requiere_archivo' => $this->requiere_archivo,
            'activo' => $this->activo,
        ], fn($v) => $v !== null);
    }
}
