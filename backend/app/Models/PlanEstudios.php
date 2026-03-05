<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanEstudios extends Model
{
    use HasUuids;

    protected $table = 'plan_estudios';

    protected $fillable = [
        'escuela_id',
        'curso_id',
        'ciclo',
        'creditos',
        'tipo',  // O = Obligatorio, E = Electivo
    ];

    protected $casts = [
        'ciclo'    => 'integer',
        'creditos' => 'integer',
    ];

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
