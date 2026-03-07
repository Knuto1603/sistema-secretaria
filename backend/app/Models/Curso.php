<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    use HasUuids;

    protected $fillable = ['codigo', 'nombre', 'area_id'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class);
    }

    public function planEstudios(): HasMany
    {
        return $this->hasMany(PlanEstudios::class);
    }

    /**
     * Cursos que son requisito previo para este curso
     */
    public function requisitos(): BelongsToMany
    {
        return $this->belongsToMany(
            Curso::class,
            'curso_requisitos',
            'curso_id',
            'requisito_curso_id'
        );
    }

    /**
     * Cursos que tienen este curso como requisito
     */
    public function prerequisitoDe(): BelongsToMany
    {
        return $this->belongsToMany(
            Curso::class,
            'curso_requisitos',
            'requisito_curso_id',
            'curso_id'
        );
    }
}
