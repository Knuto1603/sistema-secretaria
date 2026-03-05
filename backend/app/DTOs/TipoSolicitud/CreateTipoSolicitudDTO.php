<?php

namespace App\DTOs\TipoSolicitud;

readonly class CreateTipoSolicitudDTO
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $descripcion = null,
        public bool $requiere_archivo = false,
        public bool $activo = true
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            codigo: strtoupper($data['codigo']),
            nombre: $data['nombre'],
            descripcion: $data['descripcion'] ?? null,
            requiere_archivo: $data['requiere_archivo'] ?? false,
            activo: $data['activo'] ?? true
        );
    }

    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'requiere_archivo' => $this->requiere_archivo,
            'activo' => $this->activo,
        ];
    }
}
