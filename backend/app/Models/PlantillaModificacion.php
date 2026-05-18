<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantillaModificacion extends Model
{
    use HasUuids;

    protected $table = 'plantillas_modificacion';

    protected $fillable = [
        'tipo',
        'nombre_archivo',
        'ruta',
        'subido_por',
    ];

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
