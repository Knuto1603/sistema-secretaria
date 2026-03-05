<?php

namespace App\DTOs\Solicitud;

readonly class SolicitudResponseDTO
{
    public function __construct(
        public string $id,
        public string $user_id,
        public string $tipo_solicitud_id,
        public ?string $programacion_id,
        public string $motivo,
        public string $estado,
        public ?string $firma_digital_path,
        public ?string $archivo_sustento_path,
        public ?string $archivo_sustento_nombre,
        public ?string $asignado_a,
        public ?string $observaciones_admin,
        public ?array $metadatos,
        public ?array $user,
        public ?array $tipo_solicitud,
        public ?array $programacion,
        public string $created_at,
        public string $updated_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tipo_solicitud_id' => $this->tipo_solicitud_id,
            'programacion_id' => $this->programacion_id,
            'motivo' => $this->motivo,
            'estado' => $this->estado,
            'firma_digital_path' => $this->firma_digital_path,
            'archivo_sustento_path' => $this->archivo_sustento_path,
            'archivo_sustento_nombre' => $this->archivo_sustento_nombre,
            'asignado_a' => $this->asignado_a,
            'observaciones_admin' => $this->observaciones_admin,
            'metadatos' => $this->metadatos,
            'user' => $this->user,
            'tipo_solicitud' => $this->tipo_solicitud,
            'programacion' => $this->programacion,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
