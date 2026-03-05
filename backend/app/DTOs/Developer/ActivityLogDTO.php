<?php

namespace App\DTOs\Developer;

class ActivityLogDTO
{
    public function __construct(
        public readonly string  $id,
        public readonly ?array  $user,
        public readonly string  $accion,
        public readonly ?string $modelo,
        public readonly ?string $modelo_id,
        public readonly ?array  $valores_anteriores,
        public readonly ?array  $valores_nuevos,
        public readonly ?string $ip,
        public readonly string  $created_at,
    ) {}
}
