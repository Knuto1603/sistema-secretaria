<?php

namespace App\DTOs\Usuario;

readonly class CreateUsuarioDTO
{
    public function __construct(
        public string $name,
        public string $username,
        public string $email,
        public string $password,
        public string $tipo_usuario = 'administrativo',
        public array $roles = []
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            username: strtolower(trim($data['username'])),
            email: strtolower(trim($data['email'])),
            password: $data['password'],
            tipo_usuario: $data['tipo_usuario'] ?? 'administrativo',
            roles: $data['roles'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'password' => $this->password,
            'tipo_usuario' => $this->tipo_usuario,
        ];
    }
}
