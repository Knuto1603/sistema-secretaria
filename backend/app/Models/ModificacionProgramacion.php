<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ModificacionProgramacion extends Model
{
    use HasUuids;

    protected $table = 'modificaciones_programacion';

    protected $fillable = [
        'periodo_id',
        'borrador_id',
        'tipo',
        'programacion_id',
        'secciones_origen_ids',
        'datos_anteriores',
        'datos_nuevos',
        'motivo',
        'estado',
        'user_id',
    ];

    protected $casts = [
        'secciones_origen_ids' => 'array',
        'datos_anteriores'     => 'array',
        'datos_nuevos'         => 'array',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function borrador(): BelongsTo
    {
        return $this->belongsTo(BorradorProgramacion::class, 'borrador_id');
    }

    public function programacion(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class, 'programacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documentos(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentoModificacionArea::class,
            'documentos_modificacion_pivot',
            'modificacion_id',
            'documento_id'
        );
    }

    public function esPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}
