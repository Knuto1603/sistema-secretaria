<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inscripcion extends Model
{
    use HasUuids;

    protected $table = 'inscripciones';

    protected $fillable = [
        'programacion_id',
        'user_id',
        'periodo_id',
        'fuente',
    ];

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class, 'programacion_id');
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }
}
