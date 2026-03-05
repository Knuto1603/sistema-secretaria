<?php

namespace App\DTOs\Programacion;

class ProgramacionFilterDTO
{
    public function __construct(
        public readonly ?string $periodo_id,
        public readonly ?string $search,
        public readonly ?int    $per_page,
        public readonly ?string $escuela_id = null, // null = sin restricción de plan
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            periodo_id: $data['periodo_id'] ?? null,
            search:     $data['search'] ?? null,
            per_page:   isset($data['per_page']) ? (int) $data['per_page'] : null,
            escuela_id: $data['escuela_id'] ?? null,
        );
    }
}
