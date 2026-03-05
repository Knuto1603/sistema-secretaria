<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Solicitud extends Model
{
    use HasUuids;

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
        'observaciones_admin'
    ];

    protected $casts = [
        'metadatos' => 'array',
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
