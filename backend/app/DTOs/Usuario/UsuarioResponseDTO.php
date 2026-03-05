<?php

namespace App\DTOs\Usuario;

use Illuminate\Support\Collection;

readonly class UsuarioResponseDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $username,
        public string $email,
        public string $tipo_usuario,
        public bool $activo,
        public Collection $roles,
        public string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'tipo_usuario' => $this->tipo_usuario,
            'activo' => $this->activo,
            'roles' => $this->roles->toArray(),
            'created_at' => $this->created_at,
        ];
    }
}
