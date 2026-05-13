<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoArea extends Model
{
    use HasUuids;

    protected $table = 'documentos_area';

    protected $fillable = [
        'generacion_id',
        'area_id',
        'nombre_archivo',
        'ruta',
        'cursos_count',
    ];

    protected $casts = [
        'cursos_count' => 'integer',
    ];

    public function generacion(): BelongsTo
    {
        return $this->belongsTo(GeneracionDocumento::class, 'generacion_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
