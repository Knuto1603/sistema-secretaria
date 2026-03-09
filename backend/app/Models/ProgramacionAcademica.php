<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ProgramacionAcademica extends Model
{
    use HasUuids;

    protected $table = 'programacion_academica';

    protected $fillable = [
        'curso_id',
        'periodo_id',
        'docente_id',
        'aula_id',
        'grupo_horario_id',
        'clave',
        'grupo',
        'seccion',
        'ciclo',
        'aula',
        'n_acta',
        'capacidad',
        'n_inscritos',
        'lleno_manual'
    ];

    protected $casts = [
        'capacidad' => 'integer',
        'n_inscritos' => 'integer',
        'ciclo' => 'integer',
        'lleno_manual' => 'boolean',
    ];

    /**
     * Verifica si el curso está lleno (ya sea por capacidad o marcado manualmente)
     */
    public function estaLleno(): bool
    {
        return $this->lleno_manual || $this->n_inscritos >= $this->capacidad;
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'programacion_id');
    }

    /**
     * Escuelas que pueden inscribirse en esta sección
     */
    public function escuelas(): BelongsToMany
    {
        return $this->belongsToMany(Escuela::class, 'programacion_escuelas', 'programacion_id', 'escuela_id');
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'aula_id');
    }

    /**
     * Alias sin conflicto de nombre con el campo texto 'aula'
     */
    public function aulaRelacion(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'aula_id');
    }

    public function grupoHorario(): BelongsTo
    {
        return $this->belongsTo(GrupoHorario::class, 'grupo_horario_id');
    }
}
