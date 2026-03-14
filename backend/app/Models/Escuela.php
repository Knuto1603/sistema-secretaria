<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escuela extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'codigo',
        'nombre',
        'nombre_corto',
    ];

    /**
     * Estudiantes de esta escuela
     */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(User::class, 'escuela_id');
    }

    /**
     * Plan de estudios de esta escuela
     */
    public function planEstudios(): HasMany
    {
        return $this->hasMany(PlanEstudios::class);
    }

    /**
     * Versiones de planes de estudio de esta escuela
     */
    public function planes(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Obtiene el plan activo de la escuela
     */
    public function planActivo(): ?Plan
    {
        return $this->planes()->where('activo', true)->first();
    }

    /**
     * Secciones de programación académica disponibles para esta escuela
     */
    public function programaciones(): BelongsToMany
    {
        return $this->belongsToMany(ProgramacionAcademica::class, 'programacion_escuelas', 'escuela_id', 'programacion_id');
    }

    /**
     * Buscar escuela por código (0, 1, 2, 3)
     */
    public static function findByCodigo(string $codigo): ?self
    {
        return self::where('codigo', $codigo)->first();
    }
}
