<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneracionModificacion extends Model
{
    use HasUuids;

    protected $table = 'generaciones_modificaciones';

    protected $fillable = [
        'periodo_id',
        'fecha_desde',
        'fecha_hasta',
        'numero_oficio',
        'generado_por',
        'generado_at',
        'total_documentos',
    ];

    protected $casts = [
        'fecha_desde'      => 'date',
        'fecha_hasta'      => 'date',
        'generado_at'      => 'datetime',
        'total_documentos' => 'integer',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoModificacionArea::class, 'generacion_id');
    }
}
