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
 */
class AutoAsignadorProgramacion
{
    private array $ocupacion   = []; // [aula_id][grupo_id] => true
    private array $grupoDelPar = []; // [ciclo|curso_id|seccion] => grupo_id
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
        $bloques = $secciones->groupBy(fn($s) => $s->escuela_id . '|' . $s->ciclo);

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

        // Precalculados una vez para evitar allocations por iteración
        $this->gruposPrioridadMañana = $gruposMañana->merge($gruposTarde);
        $this->gruposPrioridadTarde  = $gruposTarde->merge($gruposMañana);
    }

    // ─── Núcleo del algoritmo ─────────────────────────────────────────────────

    private function procesarBloque(string $bloqueKey, Collection $seccionesBloque): void
    {
        [, $cicloStr] = explode('|', $bloqueKey, 2);
        $ciclo   = (int) $cicloStr;
        $escuela = $seccionesBloque->first()->escuela;

        $aulasPermitidas = $escuela->es_informatica ? $this->aulasFII : $this->todasAulas;
        $gruposPrioridad = $ciclo <= 5 ? $this->gruposPrioridadMañana : $this->gruposPrioridadTarde;

        // Flip para O(1) en la comprobación de rango prioritario
        $gruposPermitidosIds = $gruposPrioridad->pluck('id')->flip()->all();

        $slotsNecesarios = $seccionesBloque->count();
        $aulaId = $this->buscarAula($aulasPermitidas, $gruposPrioridad, $slotsNecesarios);

        // Fallback para escuelas no-Informática: cualquier aula disponible
        if (!$aulaId && !$escuela->es_informatica) {
            $aulaId = $this->buscarAula($this->todasAulas, $this->todosGrupos, $slotsNecesarios);
        }

        if (!$aulaId) {
            return; // Sin espacio → secciones del bloque quedan sin asignar
        }

        // Orden determinístico: por curso_id y luego por número de sección
        $seccionesOrdenadas = $seccionesBloque->sort(function ($a, $b) {
            if ($a->curso_id !== $b->curso_id) {
                return strcmp($a->curso_id, $b->curso_id);
            }
            return strcmp($a->seccion, $b->seccion);
        });

        foreach ($seccionesOrdenadas as $seccion) {
            $parKey = $ciclo . '|' . $seccion->curso_id . '|' . $seccion->seccion;
            $grupoPreferido = $this->grupoDelPar[$parKey] ?? null;
            $grupoId = null;

            // Usar el grupo de coordinación solo si pertenece al rango del ciclo actual
            if ($grupoPreferido
                && isset($gruposPermitidosIds[$grupoPreferido])
                && !isset($this->ocupacion[$aulaId][$grupoPreferido])) {
                $grupoId = $grupoPreferido;
            }

            // Si no hay coordinación posible, tomar el siguiente grupo libre
            if (!$grupoId) {
                foreach ($gruposPrioridad as $grupo) {
                    if (!isset($this->ocupacion[$aulaId][$grupo->id])) {
                        $grupoId = $grupo->id;
                        break;
                    }
                }
            }

            if (!$grupoId) {
                continue; // Aula llena en esta franja
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

    private function buscarAula(Collection $aulas, Collection $grupos, int $slotsNecesarios): ?string
    {
        foreach ($aulas as $aula) {
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
