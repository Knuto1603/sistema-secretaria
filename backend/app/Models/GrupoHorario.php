<?php

namespace App\Models;

use App\Services\PlantillaHorarioService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoHorario extends Model
{
    use HasUuids;

    protected $table = 'grupos_horario';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Busca (o crea) un grupo horario por su código (ej. "G1", "G7ABH").
     * Si el grupo es nuevo y el código sigue la convención de la Plantilla
     * Horaria institucional (G + número + letras a/b/h), genera automáticamente
     * sus horarios (día + hora) en lugar de dejarlo vacío.
     */
    public static function resolverPorCodigo(string $nombre): self
    {
        $key = strtoupper(trim($nombre));

        $grupo = static::firstOrCreate(
            ['nombre' => $key],
            ['descripcion' => null, 'activo' => true]
        );

        if ($grupo->wasRecentlyCreated) {
            $detalles = app(PlantillaHorarioService::class)->generarDetalles($key);
            foreach ($detalles as $detalle) {
                $grupo->detalles()->create($detalle);
            }
        }

        return $grupo;
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(GrupoHorarioDetalle::class, 'grupo_id')
            ->orderByRaw("FIELD(dia_semana,'lunes','martes','miercoles','jueves','viernes','sabado')")
            ->orderBy('hora_inicio');
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class, 'grupo_horario_id');
    }
}
