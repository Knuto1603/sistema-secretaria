<?php

namespace App\DTOs\Usuario;

readonly class UpdateUsuarioDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $username = null,
        public ?string $email = null,
        public ?string $password = null,
        public ?array $roles = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            username: isset($data['username']) ? strtolower(trim($data['username'])) : null,
            email: isset($data['email']) ? strtolower(trim($data['email'])) : null,
            password: $data['password'] ?? null,
            roles: $data['roles'] ?? null
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->username !== null) {
            $data['username'] = $this->username;
        }
        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
