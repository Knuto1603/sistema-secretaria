<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoHorario extends Model
{
    use HasUuids;

    protected $table = 'grupos_horario';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(GrupoHorarioDetalle::class, 'grupo_id')
            ->orderByRaw("FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado')")
            ->orderBy('hora_inicio');
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class, 'grupo_horario_id');
    }
}
