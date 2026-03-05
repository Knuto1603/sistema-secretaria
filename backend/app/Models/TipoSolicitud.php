<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoSolicitud extends Model
{
    use HasUuids;

    protected $table = 'tipo_solicitudes';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'requiere_archivo',
        'activo'
    ];

    protected $casts = [
        'requiere_archivo' => 'boolean',
        'activo' => 'boolean',
    ];

    public function solicitudes(): HasMany
    {
        return $this->hasMany(Solicitud::class, 'tipo_solicitud_id');
    }
}
