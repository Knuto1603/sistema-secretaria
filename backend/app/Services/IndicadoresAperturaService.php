<?php

namespace App\Services;

use App\Models\BorradorProgramacion;
use App\Models\Curso;
use App\Models\HistorialAcademico;
use App\Models\Plan;
use App\Models\SolicitudAperturaCurso;
use Illuminate\Support\Collection;

/**
 * Calcula, para cada curso solicitado, los indicadores que ayudan a la Secretaría a
 * priorizar la decisión de apertura. Ninguno de estos indicadores bloquea ni aprueba nada
 * automáticamente — son solo apoyo visual (ver [[solicitudes-apertura]] en la bóveda).
 */
class IndicadoresAperturaService
{
    /** @var array<string, Plan|null> escuela_id => plan activo con cursos */
    private array $planPorEscuela = [];

    /** @var array<string, string|null> periodo_id => 'par'|'impar'|null */
    private array $cicloTipoPorPeriodo = [];

    /** @var array<string, string> CODIGO_NORMALIZADO => curso_id */
    private array $codigoToCursoId = [];

    /** @var array<string, array<string, bool>> user_id => set (curso_id => true) de aprobados + equivalencias */
    private array $aprobadosExpandido = [];

    private bool $catalogoCargado = false;

    /**
     * @param Collection<int, SolicitudAperturaCurso> $solicitudes ya cargadas con user.escuela, curso, periodo
     * @return array lista de indicadores por curso, ordenada por prioridad (mayor primero)
     */
    public function calcularAgrupado(Collection $solicitudes): array
    {
        if ($solicitudes->isEmpty()) {
            return [];
        }

        $this->cargarCatalogoYAprobados($solicitudes);

        $resultado = $solicitudes
            ->groupBy('curso_id')
            ->map(fn($sols, $cursoId) => $this->calcularParaCurso((string) $cursoId, $sols))
            ->values()
            ->sortByDesc(fn($item) => $item['_score'])
            ->map(function ($item) {
                unset($item['_score']);
                return $item;
            })
            ->values()
            ->toArray();

        return $resultado;
    }

    private function cargarCatalogoYAprobados(Collection $solicitudes): void
    {
        if ($this->catalogoCargado) {
            return;
        }

        $this->codigoToCursoId = Curso::query()
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn($id, $codigo) => [$this->normalizarCodigo($codigo) => $id])
            ->toArray();

        $userIds = $solicitudes->pluck('user_id')->unique()->values();

        $historial = HistorialAcademico::whereIn('user_id', $userIds)
            ->where(function ($q) {
                $q->where('nota', '>', 10)
                  ->orWhere(function ($inner) {
                      $inner->where('fuente', 'autoreporte')->whereNull('nota');
                  });
            })
            ->get(['user_id', 'curso_id']);

        $aprobadosDirectoPorUser = $historial->groupBy('user_id')
            ->map(fn($rows) => $rows->pluck('curso_id')->unique()->toArray());

        $todosAprobadosIds = $historial->pluck('curso_id')->unique()->values();
        $equivalenciasPorCurso = Curso::whereIn('id', $todosAprobadosIds)
            ->with('equivalencias:id')
            ->get()
            ->mapWithKeys(fn($c) => [$c->id => $c->equivalencias->pluck('id')->toArray()]);

        foreach ($aprobadosDirectoPorUser as $userId => $cursoIds) {
            $expandido = $cursoIds;
            foreach ($cursoIds as $cId) {
                $expandido = array_merge($expandido, $equivalenciasPorCurso[$cId] ?? []);
            }
            $this->aprobadosExpandido[$userId] = array_fill_keys(array_unique($expandido), true);
        }

        $this->catalogoCargado = true;
    }

    private function normalizarCodigo(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }

    private function planActivoDeEscuela(string $escuelaId): ?Plan
    {
        if (!array_key_exists($escuelaId, $this->planPorEscuela)) {
            $this->planPorEscuela[$escuelaId] = Plan::where('escuela_id', $escuelaId)
                ->where('activo', true)
                ->with('cursos.curso')
                ->first();
        }

        return $this->planPorEscuela[$escuelaId];
    }

    private function cicloTipoDelPeriodo(string $periodoId): ?string
    {
        if (!array_key_exists($periodoId, $this->cicloTipoPorPeriodo)) {
            $this->cicloTipoPorPeriodo[$periodoId] = BorradorProgramacion::where('periodo_id', $periodoId)
                ->where('estado', 'publicado')
                ->value('ciclo_tipo');
        }

        return $this->cicloTipoPorPeriodo[$periodoId];
    }

    /**
     * Resuelve los códigos de requisito (texto libre, ej. "MA101", "100cred.") contra el
     * catálogo de cursos. Las entradas que no matchean ningún curso (créditos acumulados,
     * errores de tipeo) se ignoran silenciosamente — mismo criterio que PlanEstudiosImport.
     */
    private function resolverRequisitosCursoIds(?array $requisitos): array
    {
        if (empty($requisitos)) {
            return [];
        }

        $ids = [];
        foreach ($requisitos as $codigo) {
            $normalizado = $this->normalizarCodigo((string) $codigo);
            if (isset($this->codigoToCursoId[$normalizado])) {
                $ids[] = $this->codigoToCursoId[$normalizado];
            }
        }

        return array_unique($ids);
    }

    private function cumplePrerequisitos(string $userId, array $requisitosCursoIds): ?bool
    {
        if (empty($requisitosCursoIds)) {
            return null; // sin prerequisitos registrados, no "todos cumplen"
        }

        $aprobados = $this->aprobadosExpandido[$userId] ?? [];

        foreach ($requisitosCursoIds as $reqId) {
            if (!isset($aprobados[$reqId])) {
                return false;
            }
        }

        return true;
    }

    private function calcularParaCurso(string $cursoId, Collection $sols): array
    {
        $curso = $sols->first()->curso;
        $periodoId = $sols->first()->periodo_id;
        $cicloTipoPeriodo = $this->cicloTipoDelPeriodo($periodoId);

        $activas = $sols->whereIn('estado', ['pendiente', 'en_revision', 'aprobada']);

        $escuelas = $sols->groupBy('escuela_id')
            ->map(fn($solsEscuela, $escuelaId) => $this->calcularParaEscuela($curso, (string) $escuelaId, $solsEscuela, $cicloTipoPeriodo))
            ->values();

        $esCadenaEnAlguna = $escuelas->contains(fn($e) => $e['es_cadena'] === true);
        $fueraDePeriodoEnAlguna = $escuelas->contains(fn($e) => $e['fuera_de_periodo'] === true);

        $porEstado = [
            'pendiente'   => $sols->where('estado', 'pendiente')->count(),
            'en_revision' => $sols->where('estado', 'en_revision')->count(),
            'aprobada'    => $sols->where('estado', 'aprobada')->count(),
            'rechazada'   => $sols->where('estado', 'rechazada')->count(),
            'anulada'     => $sols->where('estado', 'anulada')->count(),
        ];

        $totalActivas = $activas->count();

        $score = 0;
        if ($totalActivas >= 10) $score += 1000;
        if ($esCadenaEnAlguna) $score += 500;
        if ($fueraDePeriodoEnAlguna) $score += 300;
        $score += $totalActivas;

        return [
            'curso_id'         => $cursoId,
            'codigo'           => $curso->codigo,
            'nombre'           => $curso->nombre,
            'periodo_id'       => $periodoId,
            'total'            => $sols->count(),
            'total_activas'    => $totalActivas,
            'cumple_minimo'    => $totalActivas >= 10,
            'es_cadena'        => $esCadenaEnAlguna,
            'fuera_de_periodo' => $fueraDePeriodoEnAlguna,
            'por_estado'       => $porEstado,
            'por_tipo'         => [
                'nueva_apertura' => $sols->where('tipo', 'nueva_apertura')->count(),
                'cambio_grupo'   => $sols->where('tipo', 'cambio_grupo')->count(),
            ],
            'escuelas'         => $escuelas->toArray(),
            '_score'           => $score,
        ];
    }

    private function calcularParaEscuela($curso, string $escuelaId, Collection $solsEscuela, ?string $cicloTipoPeriodo): array
    {
        $escuela = $solsEscuela->first()->escuela;
        $plan = $this->planActivoDeEscuela($escuelaId);
        $planCurso = $plan?->cursos?->firstWhere('curso_id', $curso->id);

        $ciclo = $planCurso?->ciclo;
        $esCadena = false;
        $cursosCadena = [];
        $fueraDePeriodo = null;

        if ($planCurso && $ciclo !== null) {
            $cicloSiguiente = $ciclo + 1;
            $cursosCadena = $plan->cursos
                ->where('ciclo', $cicloSiguiente)
                ->filter(fn($pc) => in_array(
                    $this->normalizarCodigo($curso->codigo),
                    array_map(fn($r) => $this->normalizarCodigo((string) $r), $pc->requisitos ?? [])
                ))
                ->map(fn($pc) => ['codigo' => $pc->curso?->codigo, 'nombre' => $pc->curso?->nombre])
                ->filter(fn($c) => $c['codigo'] !== null)
                ->values()
                ->toArray();

            $esCadena = count($cursosCadena) >= 2;

            if ($cicloTipoPeriodo !== null) {
                $paridadCurso = $ciclo % 2 === 0 ? 'par' : 'impar';
                $fueraDePeriodo = $paridadCurso !== $cicloTipoPeriodo;
            }
        }

        $requisitosCursoIds = $this->resolverRequisitosCursoIds($planCurso?->requisitos);

        $solicitantes = $solsEscuela->map(function ($s) use ($requisitosCursoIds) {
            return [
                'solicitud_id'  => $s->id,
                'user_id'       => $s->user_id,
                'nombre'        => $s->user?->name,
                'codigo'        => $s->user?->codigo_universitario,
                'tipo'          => $s->tipo,
                'estado'        => $s->estado,
                'fecha'         => $s->created_at->format('d/m/Y H:i'),
                'cumple_prerequisitos' => $this->cumplePrerequisitos($s->user_id, $requisitosCursoIds),
            ];
        })->values()->toArray();

        $conPrerequisitosConocidos = collect($solicitantes)->whereNotNull('cumple_prerequisitos');
        $pctCumplePrerequisitos = $conPrerequisitosConocidos->isEmpty()
            ? null
            : round($conPrerequisitosConocidos->where('cumple_prerequisitos', true)->count() / $conPrerequisitosConocidos->count() * 100, 1);

        return [
            'escuela_id'                 => $escuelaId,
            'escuela_nombre'             => $escuela?->nombre_corto ?? $escuela?->nombre,
            'ciclo_en_plan'              => $ciclo,
            'total_solicitantes'         => $solsEscuela->count(),
            'es_cadena'                  => $esCadena,
            'cursos_cadena'              => $cursosCadena,
            'fuera_de_periodo'           => $fueraDePeriodo,
            'pct_cumple_prerequisitos'   => $pctCumplePrerequisitos,
            'solicitantes'               => $solicitantes,
        ];
    }
}
