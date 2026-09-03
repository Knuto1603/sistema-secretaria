<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudAperturaCurso extends Model
{
    use HasUuids;

    protected $table = 'solicitudes_apertura_curso';

    protected $fillable = [
        'user_id',
        'curso_id',
        'periodo_id',
        'escuela_id',
        'tipo',
        'programacion_referencia_id',
        'motivo',
        'firma_digital_path',
        'estado',
        'observaciones_admin',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function escuela(): BelongsTo
    {
        return $this->belongsTo(Escuela::class);
    }

    public function programacionReferencia(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class, 'programacion_referencia_id');
    }
}
