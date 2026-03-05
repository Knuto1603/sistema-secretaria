<?php

namespace App\DTOs\Auth;

use App\Models\User;
use Illuminate\Support\Collection;

class AuthenticatedUserDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $tipo_usuario,
        public readonly ?string $username,
        public readonly ?string $codigo_universitario,
        public readonly ?string $escuela,
        public readonly ?int $anio_ingreso,
        public readonly Collection $roles,
        public readonly Collection $permissions
    ) {}

    public static function fromUser(User $user): self
    {
        // Cargar la escuela si no está cargada
        if ($user->escuela_id && !$user->relationLoaded('escuela')) {
            $user->load('escuela');
        }

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            tipo_usuario: $user->tipo_usuario,
            username: $user->username,
            codigo_universitario: $user->codigo_universitario,
            escuela: $user->escuela?->nombre_corto,
            anio_ingreso: $user->anio_ingreso,
            roles: $user->getRoleNames(),
            permissions: $user->getAllPermissions()->pluck('name')
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'tipo_usuario' => $this->tipo_usuario,
            'username' => $this->username,
            'codigo_universitario' => $this->codigo_universitario,
            'escuela' => $this->escuela,
            'anio_ingreso' => $this->anio_ingreso,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
        ];
    }
}
