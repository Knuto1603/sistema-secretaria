<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BorradorSeccion extends Model
{
    use HasUuids;

    protected $table = 'borradores_secciones';

    protected $fillable = [
        'borrador_id',
        'curso_id',
        'escuela_id',
        'ciclo',
        'tipo',
        'seccion',
        'docente_id',
        'aula_id',
        'grupo_horario_id',
        'capacidad',
    ];

    protected $casts = [
        'ciclo'     => 'integer',
        'capacidad' => 'integer',
    ];

    public function borrador(): BelongsTo
    {
        return $this->belongsTo(BorradorProgramacion::class, 'borrador_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    public function grupoHorario(): BelongsTo
    {
        return $this->belongsTo(GrupoHorario::class, 'grupo_horario_id');
    }

    public function estaAsignado(): bool
    {
        return $this->aula_id !== null && $this->grupo_horario_id !== null;
    }
}
