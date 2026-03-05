<?php

namespace App\DTOs\Auth;

readonly class LoginAdminDTO
{
    public function __construct(
        public string $username,
        public string $password,
        public string $device_name = 'web-browser'
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            username: $data['username'],
            password: $data['password'],
            device_name: $data['device_name'] ?? 'web-browser'
        );
    }
}
