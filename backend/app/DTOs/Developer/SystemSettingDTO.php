<?php

namespace App\DTOs\Developer;

class SystemSettingDTO
{
    public function __construct(
        public readonly string  $key,
        public readonly ?string $value,
        public readonly string  $type,
        public readonly string  $grupo,
        public readonly ?string $descripcion,
    ) {}
}
