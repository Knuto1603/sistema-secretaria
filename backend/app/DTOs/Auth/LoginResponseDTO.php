<?php

namespace App\DTOs\Auth;

class LoginResponseDTO
{
    public function __construct(
        public readonly string $status,
        public readonly string $access_token,
        public readonly string $token_type,
        public readonly AuthenticatedUserDTO $user
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'access_token' => $this->access_token,
            'token_type' => $this->token_type,
            'user' => $this->user->toArray(),
        ];
    }
}
