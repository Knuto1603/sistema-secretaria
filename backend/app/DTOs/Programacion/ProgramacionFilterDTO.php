<?php

namespace App\DTOs\Programacion;

class ProgramacionFilterDTO
{
    public function __construct(
        public readonly ?string $periodo_id,
        public readonly ?string $search,
        public readonly ?int    $per_page,
        public readonly ?string $escuela_id           = null,
        public readonly ?int    $ciclo                = null,
        public readonly ?string $area_id              = null,
        public readonly ?string $grupo                = null,
        public readonly ?string $escuela_programada_id = null,
    public readonly ?string $tipo                 = null,  // O = Obligatorio, E = Electivo
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            periodo_id:            $data['periodo_id'] ?? null,
            search:                $data['search'] ?? null,
            per_page:              isset($data['per_page']) ? (int) $data['per_page'] : null,
            escuela_id:            $data['escuela_id'] ?? null,
            ciclo:                 isset($data['ciclo']) ? (int) $data['ciclo'] : null,
            area_id:               $data['area_id'] ?? null,
            grupo:                 $data['grupo'] ?? null,
            escuela_programada_id: $data['escuela_programada_id'] ?? null,
            tipo:                  isset($data['tipo']) && in_array($data['tipo'], ['O', 'E']) ? $data['tipo'] : null,
        );
    }
}
