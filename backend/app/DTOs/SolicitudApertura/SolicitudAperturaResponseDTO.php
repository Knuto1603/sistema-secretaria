<?php

namespace App\DTOs\SolicitudApertura;

readonly class SolicitudAperturaResponseDTO
{
    public function __construct(
        public string $id,
        public string $user_id,
        public string $curso_id,
        public string $periodo_id,
        public string $escuela_id,
        public string $tipo,
        public ?string $programacion_referencia_id,
        public string $motivo,
        public ?string $firma_digital_path,
        public string $estado,
        public ?string $observaciones_admin,
        public ?array $user,
        public ?array $curso,
        public ?array $periodo,
        public ?array $escuela,
        public ?array $programacion_referencia,
        public string $created_at,
        public string $updated_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'curso_id' => $this->curso_id,
            'periodo_id' => $this->periodo_id,
            'escuela_id' => $this->escuela_id,
            'tipo' => $this->tipo,
            'programacion_referencia_id' => $this->programacion_referencia_id,
            'motivo' => $this->motivo,
            'firma_digital_path' => $this->firma_digital_path,
            'estado' => $this->estado,
            'observaciones_admin' => $this->observaciones_admin,
            'user' => $this->user,
            'curso' => $this->curso,
            'periodo' => $this->periodo,
            'escuela' => $this->escuela,
            'programacion_referencia' => $this->programacion_referencia,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
