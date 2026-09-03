<?php

namespace App\DTOs\SolicitudApertura;

class CreateSolicitudAperturaDTO
{
    public function __construct(
        public readonly string $curso_id,
        public readonly string $motivo,
        public readonly string $firma,
        public readonly string $tipo = 'nueva_apertura',
        public readonly ?string $programacion_referencia_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            curso_id: $data['curso_id'],
            motivo: $data['motivo'],
            firma: $data['firma'],
            tipo: $data['tipo'] ?? 'nueva_apertura',
            programacion_referencia_id: $data['programacion_referencia_id'] ?? null
        );
    }
}
