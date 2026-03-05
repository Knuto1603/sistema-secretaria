<?php

namespace App\DTOs\Programacion;

readonly class ProgramacionResponseDTO
{
    public function __construct(
        public string $id,
        public string $curso_id,
        public string $periodo_id,
        public ?string $docente_id,
        public string $clave,
        public string $grupo,
        public ?string $seccion,
        public ?string $aula,
        public ?string $n_acta,
        public ?int $capacidad,
        public ?int $n_inscritos,
        public bool $lleno_manual,
        public bool $esta_lleno,
        public ?array $curso,
        public ?array $periodo,
        public ?array $docente,
        public string $created_at
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
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
            'lleno_manual' => $this->lleno_manual,
            'esta_lleno' => $this->esta_lleno,
            'curso' => $this->curso,
            'periodo' => $this->periodo,
            'docente' => $this->docente,
            'created_at' => $this->created_at,
        ];
    }
}
