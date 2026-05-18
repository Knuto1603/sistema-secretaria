<?php

namespace App\Transformers;

use App\Models\ModificacionProgramacion;

class ModificacionTransformer
{
    public function toArray(ModificacionProgramacion $m): array
    {
        $programacion = null;
        if ($m->relationLoaded('programacion') && $m->programacion) {
            $prog = $m->programacion;
            $programacion = [
                'id'     => $prog->id,
                'grupo'  => $prog->grupo,
                'seccion'=> $prog->seccion,
                'ciclo'  => $prog->ciclo,
                'curso'  => $prog->relationLoaded('curso') && $prog->curso ? [
                    'id'     => $prog->curso->id,
                    'nombre' => $prog->curso->nombre,
                    'codigo' => $prog->curso->codigo,
                    'area'   => $prog->curso->relationLoaded('area') && $prog->curso->area ? [
                        'id'          => $prog->curso->area->id,
                        'nombre'      => $prog->curso->area->nombre,
                        'nombre_tabla'=> $prog->curso->area->nombre_tabla,
                    ] : null,
                ] : null,
                'aula' => $prog->relationLoaded('aulaRelacion') && $prog->aulaRelacion ? [
                    'id'    => $prog->aulaRelacion->id,
                    'nombre'=> $prog->aulaRelacion->nombre,
                ] : null,
                'grupo_horario' => $prog->relationLoaded('grupoHorario') && $prog->grupoHorario ? [
                    'id'    => $prog->grupoHorario->id,
                    'nombre'=> $prog->grupoHorario->nombre,
                ] : null,
            ];
        }

        return [
            'id'                  => $m->id,
            'tipo'                => $m->tipo,
            'estado'              => $m->estado,
            'motivo'              => $m->motivo,
            'datos_anteriores'    => $m->datos_anteriores,
            'datos_nuevos'        => $m->datos_nuevos,
            'secciones_origen_ids'=> $m->secciones_origen_ids,
            'periodo'             => $m->relationLoaded('periodo') && $m->periodo
                ? ['id' => $m->periodo->id, 'nombre' => $m->periodo->nombre]
                : null,
            'programacion'        => $programacion,
            'usuario'             => $m->relationLoaded('user') && $m->user
                ? ['id' => $m->user->id, 'nombre' => $m->user->name]
                : null,
            'created_at'          => $m->created_at?->toIso8601String(),
        ];
    }

    public function collection(iterable $items): array
    {
        return collect($items)->map(fn ($m) => $this->toArray($m))->values()->all();
    }
}
