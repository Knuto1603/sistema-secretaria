<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentoModificacionArea extends Model
{
    use HasUuids;

    protected $table = 'documentos_modificacion_area';

    protected $fillable = [
        'generacion_id',
        'area_id',
        'tipo_documento',
        'nombre_archivo',
        'ruta',
        'modificaciones_count',
    ];

    protected $casts = [
        'modificaciones_count' => 'integer',
    ];

    public function generacion(): BelongsTo
    {
        return $this->belongsTo(GeneracionModificacion::class, 'generacion_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function modificaciones(): BelongsToMany
    {
        return $this->belongsToMany(
            ModificacionProgramacion::class,
            'documentos_modificacion_pivot',
            'documento_id',
            'modificacion_id'
        );
    }
}
