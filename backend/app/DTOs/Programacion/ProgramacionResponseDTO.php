<?php

namespace App\DTOs\Programacion;

readonly class ProgramacionResponseDTO
{
    public function __construct(
        public string $id,
        public string $curso_id,
        public string $periodo_id,
        public ?string $docente_id,
        public ?string $grupo_horario_id,
        public ?string $aula_id,
        public string $clave,
        public string $grupo,
        public ?string $seccion,
        public ?string $aula,
        public ?string $aula_nombre,
        public ?string $n_acta,
        public ?int $capacidad,
        public ?int $n_inscritos,
        public bool $lleno_manual,
        public bool $esta_lleno,
        public ?array $curso,
        public ?array $periodo,
        public ?array $docente,
        public ?array $aula_rel,
        public ?array $grupo_horario,
        public ?array $escuelas,
        public ?array $escuela_programada,
        public string $created_at,
        public bool $es_equivalente = false,
        public ?string $tipo_plan = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'curso_id'            => $this->curso_id,
            'periodo_id'          => $this->periodo_id,
            'docente_id'          => $this->docente_id,
            'grupo_horario_id'    => $this->grupo_horario_id,
            'aula_id'             => $this->aula_id,
            'clave'               => $this->clave,
            'grupo'               => $this->grupo,
            'seccion'             => $this->seccion,
            'aula'                => $this->aula,
            'aula_nombre'         => $this->aula_nombre,
            'n_acta'              => $this->n_acta,
            'capacidad'           => $this->capacidad,
            'n_inscritos'         => $this->n_inscritos,
            'lleno_manual'        => $this->lleno_manual,
            'esta_lleno'          => $this->esta_lleno,
            'curso'               => $this->curso,
            'periodo'             => $this->periodo,
            'docente'             => $this->docente,
            'aula_rel'            => $this->aula_rel,
            'grupo_horario'       => $this->grupo_horario,
            'escuelas'            => $this->escuelas,
            'escuela_programada'  => $this->escuela_programada,
            'created_at'          => $this->created_at,
            'es_equivalente'      => $this->es_equivalente,
            'tipo_plan'           => $this->tipo_plan,
        ];
    }
}
