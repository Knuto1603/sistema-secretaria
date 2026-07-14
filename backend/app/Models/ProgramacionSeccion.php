<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramacionSeccion extends Model
{
    use HasUuids;

    protected $table = 'programacion_secciones';

    protected $fillable = [
        'programacion_id',
        'curso_id',
        'escuela_programada_id',
        'ciclo',
        'tipo',
        'seccion',
        'docente_id',
        'aula_id',
        'grupo_horario_id',
        'capacidad',
        'n_inscritos',
        'lleno_manual',
        'activo',
        'clave',
        'grupo',
        'aula',
        'n_acta',
    ];

    protected $casts = [
        'ciclo'        => 'integer',
        'capacidad'    => 'integer',
        'n_inscritos'  => 'integer',
        'lleno_manual' => 'boolean',
        'activo'       => 'boolean',
    ];

    public function estaLleno(): bool
    {
        return $this->lleno_manual || $this->n_inscritos >= $this->capacidad;
    }

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(Programacion::class, 'programacion_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function escuelaProgramada(): BelongsTo
    {
        return $this->belongsTo(Escuela::class, 'escuela_programada_id');
    }

    public function escuelas(): BelongsToMany
    {
        return $this->belongsToMany(Escuela::class, 'programacion_escuelas', 'programacion_id', 'escuela_id');
    }

    public function aulaRelacion(): BelongsTo
    {
        return $this->belongsTo(Aula::class, 'aula_id');
    }

    public function grupoHorario(): BelongsTo
    {
        return $this->belongsTo(GrupoHorario::class, 'grupo_horario_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'programacion_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'programacion_id');
    }
}
