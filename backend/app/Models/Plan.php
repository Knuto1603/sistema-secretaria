<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $table = 'planes_estudios';

    protected $fillable = [
        'nombre',
        'escuela_id',
        'activo',
        'total_creditos_obligatorios',
        'creditos_electivos_requeridos',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'total_creditos_obligatorios' => 'integer',
        'creditos_electivos_requeridos' => 'integer',
    ];

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(PlanEstudios::class, 'plan_id');
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
