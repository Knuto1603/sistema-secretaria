<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneracionDocumento extends Model
{
    use HasUuids;

    protected $table = 'generaciones_documentos';

    protected $fillable = [
        'borrador_id',
        'periodo_id',
        'numero_oficio',
        'semestre_texto',
        'generado_por',
        'generado_at',
        'total_documentos',
    ];

    protected $casts = [
        'generado_at'      => 'datetime',
        'total_documentos' => 'integer',
    ];

    public function borrador(): BelongsTo
    {
        return $this->belongsTo(BorradorProgramacion::class, 'borrador_id');
    }

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
        return $this->hasMany(DocumentoArea::class, 'generacion_id');
    }
}
