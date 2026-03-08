<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoHorarioDetalle extends Model
{
    use HasUuids;

    protected $table = 'grupos_horario_detalle';

    protected $fillable = [
        'grupo_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoHorario::class, 'grupo_id');
    }
}
