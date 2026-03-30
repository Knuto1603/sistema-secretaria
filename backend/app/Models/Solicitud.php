<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Solicitud extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'solicitud';

    protected $fillable = [
        'user_id',
        'tipo_solicitud_id',
        'programacion_id',
        'metadatos',
        'motivo',
        'estado',
        'firma_digital_path',
        'archivo_sustento_path',
        'archivo_sustento_nombre',
        'asignado_a',
        'observaciones_admin',
        'fuera_de_plan'
    ];

    protected $casts = [
        'metadatos'    => 'array',
        'fuera_de_plan' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipoSolicitud(): BelongsTo
    {
        return $this->belongsTo(TipoSolicitud::class, 'tipo_solicitud_id');
    }

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class, 'programacion_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }
}
