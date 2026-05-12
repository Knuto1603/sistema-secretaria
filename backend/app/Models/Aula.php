<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aula extends Model
{
    use HasUuids;

    protected $table = 'aulas';

    protected $fillable = [
        'pabellon_id',
        'nombre',
        'capacidad',
        'activo',
        'es_laboratorio',
    ];

    protected $casts = [
        'capacidad'      => 'integer',
        'activo'         => 'boolean',
        'es_laboratorio' => 'boolean',
    ];

    public function pabellon(): BelongsTo
    {
        return $this->belongsTo(Pabellon::class);
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class, 'aula_id');
    }
}
