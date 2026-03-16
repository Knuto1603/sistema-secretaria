<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;

class ProgresoAcademicoService
{
    /**
     * Calcula el progreso académico de un estudiante según su plan activo.
     */
    public function calcularProgreso(User $user): array
    {
        if (!$user->escuela_id) {
            return $this->progresoVacio('El estudiante no tiene escuela asignada.');
        }

        // Obtener plan activo de la escuela
        $plan = Plan::where('escuela_id', $user->escuela_id)
            ->where('activo', true)
            ->with(['cursos.curso.equivalencias'])
            ->first();

        if (!$plan) {
            return $this->progresoVacio('No hay un plan de estudios activo para tu escuela.');
        }

        // IDs de cursos aprobados por el alumno
        $aprobadosIds = $user->cursosAprobados()->pluck('cursos.id')->toArray();

        // También considerar equivalencias de cada curso aprobado
        $aprobadosConEquivalencias = $this->expandirConEquivalencias($aprobadosIds, $user);

        $obligatoriosRequeridos = 0;
        $obligatoriosHechos     = 0;
        $electivosHechos        = 0;
        $pendientesObligatorios = [];

        foreach ($plan->cursos as $planCurso) {
            $curso    = $planCurso->curso;
            $creditos = $planCurso->creditos ?? 0;

            // Ignorar entradas del plan cuyo curso ya no existe en la BD
            if (!$curso) {
                continue;
            }

            // Verificar si el curso está aprobado directamente o vía equivalencia
            $aprobado = in_array($curso->id, $aprobadosConEquivalencias);

            if ($planCurso->tipo === 'O') {
                $obligatoriosRequeridos += $creditos;
                if ($aprobado) {
                    $obligatoriosHechos += $creditos;
                } else {
                    $pendientesObligatorios[] = [
                        'ciclo'    => $planCurso->ciclo,
                        'codigo'   => $curso->codigo,
                        'nombre'   => $curso->nombre,
                        'creditos' => $creditos,
                    ];
                }
            } elseif ($planCurso->tipo === 'E' && $aprobado) {
                $electivosHechos += $creditos;
            }
        }

        $electivosRequeridos = $plan->creditos_electivos_requeridos;
        $electivosHechosFinal = min($electivosHechos, $electivosRequeridos);

        $egresanteCalculado = $obligatoriosHechos >= $obligatoriosRequeridos
            && $electivosHechosFinal >= $electivosRequeridos;

        // Marcar automáticamente como egresante si cumple y no estaba marcado
        if ($egresanteCalculado && !$user->egresante) {
            $user->update(['egresante' => true]);
        }

        // Agrupar pendientes por ciclo
        $pendientesPorCiclo = collect($pendientesObligatorios)
            ->sortBy('ciclo')
            ->groupBy('ciclo')
            ->map(fn($items, $ciclo) => [
                'ciclo'  => (int) $ciclo,
                'cursos' => $items->values()->toArray(),
            ])
            ->values()
            ->toArray();

        return [
            'plan' => [
                'id'     => $plan->id,
                'nombre' => $plan->nombre,
                'activo' => $plan->activo,
            ],
            'obligatorios' => [
                'requeridos'  => $obligatoriosRequeridos,
                'hechos'      => $obligatoriosHechos,
                'porcentaje'  => $obligatoriosRequeridos > 0
                    ? round($obligatoriosHechos / $obligatoriosRequeridos * 100, 1)
                    : 0,
                'pendientes_por_ciclo' => $pendientesPorCiclo,
            ],
            'electivos' => [
                'requeridos' => $electivosRequeridos,
                'hechos'     => $electivosHechosFinal,
                'porcentaje' => $electivosRequeridos > 0
                    ? round($electivosHechosFinal / $electivosRequeridos * 100, 1)
                    : 0,
            ],
            'egresante_calculado' => $egresanteCalculado,
            'egresante_manual'    => (bool) $user->egresante,
        ];
    }

    /**
     * Expande el array de IDs aprobados incluyendo los IDs
     * de cursos que tienen equivalencias con los aprobados.
     */
    private function expandirConEquivalencias(array $aprobadosIds, User $user): array
    {
        if (empty($aprobadosIds)) {
            return [];
        }

        // Cargar equivalencias de todos los cursos aprobados
        $equivalencias = \App\Models\Curso::whereIn('id', $aprobadosIds)
            ->with('equivalencias:id')
            ->get()
            ->flatMap(fn($curso) => $curso->equivalencias->pluck('id'))
            ->toArray();

        return array_unique(array_merge($aprobadosIds, $equivalencias));
    }

    private function progresoVacio(string $mensaje): array
    {
        return [
            'plan'                => null,
            'obligatorios'        => ['requeridos' => 0, 'hechos' => 0, 'porcentaje' => 0, 'pendientes_por_ciclo' => []],
            'electivos'           => ['requeridos' => 0, 'hechos' => 0, 'porcentaje' => 0],
            'egresante_calculado' => false,
            'egresante_manual'    => false,
            'mensaje'             => $mensaje,
        ];
    }
}
