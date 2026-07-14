<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programacion extends Model
{
    use HasUuids;

    protected $table = 'programaciones';

    protected $fillable = [
        'periodo_id',
        'nombre',
        'ciclo_tipo',
        'estado',
        'creado_por',
        'publicado_por',
        'publicado_at',
    ];

    protected $casts = [
        'publicado_at' => 'datetime',
    ];

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function publicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(ProgramacionSeccion::class, 'programacion_id');
    }

    public function modificaciones(): HasMany
    {
        return $this->hasMany(ModificacionProgramacion::class, 'borrador_id');
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function estaPublicado(): bool
    {
        return $this->estado === 'publicado';
    }
}
