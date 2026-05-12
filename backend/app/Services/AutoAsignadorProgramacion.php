<?php

namespace App\Services;

use App\Models\Aula;
use App\Models\GrupoHorario;
use Illuminate\Support\Collection;

/**
 * Algoritmo greedy de auto-asignación de secciones a celdas [Aula × GrupoHorario].
 *
 * Hard constraints garantizados:
 *  - Sin laboratorios (aula.es_laboratorio = true excluidas)
 *  - Informática (escuela.es_informatica) → solo aulas FII (pabellón contiene "Industrial")
 *  - Sin colisiones: cada celda [aula_id, grupo_id] es ocupada por máximo una sección
 *  - (Escuela, Ciclo) → misma aula (todos los cursos del bloque en la misma columna)
 *  - Mismo (ciclo, curso_id, seccion) → mismo grupo en distintas escuelas (coordinación)
 *  - Grupo preferido solo si pertenece al rango de prioridad del ciclo (mañana/tarde)
 *  - Ciclos I–V: prioridad G1–G8; Ciclos VI–X: prioridad G9+
 *
 * Estrategia de distribución: "least-loaded" → siempre asigna el bloque al aula
 * con menor ocupación actual, evitando que unas pocas aulas concentren todo el uso.
 */
class AutoAsignadorProgramacion
{
    private array $ocupacion    = []; // [aula_id][grupo_id] => true
    private array $grupoDelPar  = []; // [ciclo|curso_id|seccion] => grupo_id
    private array $asignaciones = []; // [seccion_id] => [aula_id, grupo_horario_id]

    private Collection $todasAulas;
    private Collection $aulasFII;
    private Collection $todosGrupos;
    private Collection $gruposPrioridadMañana;
    private Collection $gruposPrioridadTarde;

    public function __construct()
    {
        $this->cargarRecursos();
    }

    /**
     * Distribuye las secciones del borrador en celdas disponibles.
     * Retorna mapa [seccion_id => [aula_id, grupo_horario_id]].
     */
    public function distribuir(Collection $secciones): array
    {
        // Agrupar por (escuela, ciclo, seccion): la sección "1" va en un aula,
        // la sección "2" en otra, pero usando los mismos grupos horarios gracias a grupoDelPar.
        $bloques = $secciones->groupBy(fn($s) => $s->escuela_id . '|' . $s->ciclo . '|' . $s->seccion);

        // Informática primero → reserva aulas FII antes que las demás escuelas
        $bloquesOrdenados = $bloques->sortBy(function (Collection $secs) {
            $ciclo = (int) $secs->first()->ciclo;
            return ($secs->first()->escuela->es_informatica ? 0 : 100) + $ciclo;
        });

        foreach ($bloquesOrdenados as $bloqueKey => $seccionesBloque) {
            $this->procesarBloque($bloqueKey, $seccionesBloque);
        }

        return $this->asignaciones;
    }

    /**
     * Resumen de uso de aulas tras ejecutar distribuir().
     * Útil para diagnóstico: muestra cuántos slots usa cada aula y cuáles quedaron vacías.
     */
    public function resumenAulas(): array
    {
        $totalGrupos = $this->todosGrupos->count();

        $usadas = [];
        foreach ($this->ocupacion as $aulaId => $grupos) {
            $aula = $this->todasAulas->firstWhere('id', $aulaId);
            $usadas[] = [
                'aula'     => $aula?->nombre ?? $aulaId,
                'pabellon' => $aula?->pabellon?->nombre ?? '—',
                'slots_usados' => count($grupos),
                'slots_total'  => $totalGrupos,
            ];
        }

        $idsUsadas = array_keys($this->ocupacion);
        $vacias = $this->todasAulas
            ->whereNotIn('id', $idsUsadas)
            ->map(fn($a) => [
                'aula'         => $a->nombre,
                'pabellon'     => $a->pabellon?->nombre ?? '—',
                'slots_usados' => 0,
                'slots_total'  => $totalGrupos,
                'razon'        => 'No fue necesaria — aulas anteriores tuvieron capacidad suficiente',
            ])
            ->values()
            ->all();

        return [
            'usadas' => $usadas,
            'vacias' => $vacias,
        ];
    }

    // ─── Recursos ─────────────────────────────────────────────────────────────

    private function cargarRecursos(): void
    {
        $this->todasAulas = Aula::with('pabellon')
            ->where('activo', true)
            ->where('es_laboratorio', false)
            ->get();

        $this->aulasFII = $this->todasAulas->filter(
            fn($a) => str_contains(strtolower($a->pabellon?->nombre ?? ''), 'industrial')
        )->values();

        $this->todosGrupos = GrupoHorario::where('activo', true)
            ->get()
            ->sortBy(fn($g) => $this->extraerNumeroGrupo($g->nombre))
            ->values();

        $gruposMañana = $this->todosGrupos->filter(fn($g) => $this->extraerNumeroGrupo($g->nombre) <= 8)->values();
        $gruposTarde  = $this->todosGrupos->filter(fn($g) => $this->extraerNumeroGrupo($g->nombre) > 8)->values();

        $this->gruposPrioridadMañana = $gruposMañana->merge($gruposTarde);
        $this->gruposPrioridadTarde  = $gruposTarde->merge($gruposMañana);
    }

    // ─── Núcleo del algoritmo ─────────────────────────────────────────────────

    private function procesarBloque(string $bloqueKey, Collection $seccionesBloque): void
    {
        $ciclo   = (int) $seccionesBloque->first()->ciclo;
        $escuela = $seccionesBloque->first()->escuela;

        $aulasPermitidas = $escuela->es_informatica ? $this->aulasFII : $this->todasAulas;
        $gruposPrioridad = $ciclo <= 5 ? $this->gruposPrioridadMañana : $this->gruposPrioridadTarde;
        $gruposPermitidosIds = $gruposPrioridad->pluck('id')->flip()->all();

        $slotsNecesarios = $seccionesBloque->count();
        $aulaId = $this->buscarAula($aulasPermitidas, $gruposPrioridad, $slotsNecesarios);

        if (!$aulaId && !$escuela->es_informatica) {
            $aulaId = $this->buscarAula($this->todasAulas, $this->todosGrupos, $slotsNecesarios);
        }

        if (!$aulaId) {
            return;
        }

        $seccionesOrdenadas = $seccionesBloque->sort(fn($a, $b) => strcmp($a->curso_id, $b->curso_id));

        foreach ($seccionesOrdenadas as $seccion) {
            // Clave sin sección: mismo curso del mismo ciclo → mismo grupo siempre,
            // independientemente de si es sección 1 o 2, y de la escuela.
            $parKey = $ciclo . '|' . $seccion->curso_id;
            $grupoPreferido = $this->grupoDelPar[$parKey] ?? null;
            $grupoId = null;

            if ($grupoPreferido
                && isset($gruposPermitidosIds[$grupoPreferido])
                && !isset($this->ocupacion[$aulaId][$grupoPreferido])) {
                $grupoId = $grupoPreferido;
            }

            if (!$grupoId) {
                foreach ($gruposPrioridad as $grupo) {
                    if (!isset($this->ocupacion[$aulaId][$grupo->id])) {
                        $grupoId = $grupo->id;
                        break;
                    }
                }
            }

            if (!$grupoId) {
                continue;
            }

            $this->asignaciones[$seccion->id] = [
                'aula_id'          => $aulaId,
                'grupo_horario_id' => $grupoId,
            ];
            $this->ocupacion[$aulaId][$grupoId] = true;
            $this->grupoDelPar[$parKey] = $grupoId;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Selecciona el aula con menor ocupación actual que tenga slots suficientes
     * en el rango de grupos indicado. Estrategia "least-loaded" para distribuir
     * la carga entre todas las aulas disponibles en lugar de saturar las primeras.
     */
    private function buscarAula(Collection $aulas, Collection $grupos, int $slotsNecesarios): ?string
    {
        // Ordenar de menor a mayor ocupación para distribuir carga uniformemente
        $aulasOrdenadas = $aulas->sortBy(fn($a) => count($this->ocupacion[$a->id] ?? []));

        foreach ($aulasOrdenadas as $aula) {
            $libres = 0;
            foreach ($grupos as $grupo) {
                if (!isset($this->ocupacion[$aula->id][$grupo->id])) {
                    $libres++;
                }
                if ($libres >= $slotsNecesarios) {
                    return $aula->id;
                }
            }
        }
        return null;
    }

    private function extraerNumeroGrupo(string $nombre): int
    {
        preg_match('/\d+/', $nombre, $matches);
        return isset($matches[0]) ? (int) $matches[0] : 999;
    }
}
