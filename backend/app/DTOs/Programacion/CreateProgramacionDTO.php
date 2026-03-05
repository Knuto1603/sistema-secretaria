<?php

namespace App\DTOs\Programacion;

readonly class CreateProgramacionDTO
{
    public function __construct(
        public string $curso_id,
        public string $periodo_id,
        public ?string $docente_id,
        public string $clave,
        public string $grupo,
        public ?string $seccion = null,
        public ?string $aula = null,
        public ?string $n_acta = null,
        public ?int $capacidad = null,
        public ?int $n_inscritos = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            curso_id: $data['curso_id'],
            periodo_id: $data['periodo_id'],
            docente_id: $data['docente_id'] ?? null,
            clave: $data['clave'],
            grupo: $data['grupo'],
            seccion: $data['seccion'] ?? null,
            aula: $data['aula'] ?? null,
            n_acta: $data['n_acta'] ?? null,
            capacidad: isset($data['capacidad']) ? (int) $data['capacidad'] : null,
            n_inscritos: isset($data['n_inscritos']) ? (int) $data['n_inscritos'] : null
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'curso_id' => $this->curso_id,
            'periodo_id' => $this->periodo_id,
            'docente_id' => $this->docente_id,
            'clave' => $this->clave,
            'grupo' => $this->grupo,
            'seccion' => $this->seccion,
            'aula' => $this->aula,
            'n_acta' => $this->n_acta,
            'capacidad' => $this->capacidad,
            'n_inscritos' => $this->n_inscritos,
        ], fn($v) => $v !== null);
    }
}
