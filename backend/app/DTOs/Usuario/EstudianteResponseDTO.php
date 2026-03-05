<?php

namespace App\DTOs\Usuario;

readonly class EstudianteResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $codigo_universitario,
        public string $email,
        public ?string $escuela,
        public ?int $anio_ingreso,
        public bool $cuenta_activada,
        public bool $activo,
        public ?string $password_set_at,
        public ?string $ultimo_otp_enviado,
        public string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'codigo_universitario' => $this->codigo_universitario,
            'email' => $this->email,
            'escuela' => $this->escuela,
            'anio_ingreso' => $this->anio_ingreso,
            'cuenta_activada' => $this->cuenta_activada,
            'activo' => $this->activo,
            'password_set_at' => $this->password_set_at,
            'ultimo_otp_enviado' => $this->ultimo_otp_enviado,
            'created_at' => $this->created_at,
        ];
    }
}
