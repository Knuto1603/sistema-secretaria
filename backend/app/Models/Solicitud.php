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
        'periodo_id',
        'metadatos',
        'motivo',
        'estado',
        'firma_digital_path',
        'archivo_sustento_path',
        'archivo_sustento_nombre',
        'constancia_pdf_path',
        'asignado_a',
        'observaciones_admin',
        'fuera_de_plan',
        'respuesta_alumno',
        'fecha_respuesta',
        'respuesta_admin',
    ];

    protected $casts = [
        'metadatos'       => 'array',
        'fuera_de_plan'   => 'boolean',
        'fecha_respuesta' => 'datetime',
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

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    public function asignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }
}
