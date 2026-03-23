<?php

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProgramacionRepository implements ProgramacionRepositoryInterface
{
    public function __construct(
        protected ProgramacionAcademica $model
    ) {}

    public function getByPeriodoWithFilters(string $periodoId, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->getBaseQuery($periodoId);

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('clave', 'like', "%{$search}%")
                    ->orWhere('grupo', 'like', "%{$search}%")
                    ->orWhereHas('curso', function (Builder $q) use ($search) {
                        $q->where('nombre', 'like', "%{$search}%")
                            ->orWhere('codigo', 'like', "%{$search}%");
                    })
                    ->orWhereHas('docente', function (Builder $q) use ($search) {
                        $q->where('nombre_completo', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function findById(string $id): ?ProgramacionAcademica
    {
        return $this->model
            ->with(['curso.area', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas', 'escuelaProgramada'])
            ->find($id);
    }

    public function deleteByPeriodo(string $periodoId): array
    {
        return DB::transaction(function () use ($periodoId) {
            $ids = $this->model->where('periodo_id', $periodoId)->pluck('id');

            if ($ids->isEmpty()) {
                return ['programacion' => 0, 'inscripciones' => 0, 'solicitudes' => 0];
            }

            $solicitudes    = DB::table('solicitud')->whereIn('programacion_id', $ids)->delete();
            $inscripciones  = DB::table('inscripciones')->whereIn('programacion_id', $ids)->delete();
            DB::table('programacion_escuelas')->whereIn('programacion_id', $ids)->delete();

            $programacion = $this->model->where('periodo_id', $periodoId)->delete();

            return [
                'programacion'  => $programacion,
                'inscripciones' => $inscripciones,
                'solicitudes'   => $solicitudes,
            ];
        });
    }

    public function delete(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $prog = $this->model->find($id);
            if (!$prog) return false;

            DB::table('solicitud')->where('programacion_id', $id)->delete();
            DB::table('inscripciones')->where('programacion_id', $id)->delete();
            DB::table('programacion_escuelas')->where('programacion_id', $id)->delete();

            return (bool) $prog->delete();
        });
    }

    public function getBaseQuery(string $periodoId, ?string $escuelaId = null, ?int $ciclo = null, ?string $areaId = null, ?string $grupo = null, ?string $escuelaProgramadaId = null, array $codigosEquivalentes = [], ?string $tipo = null): Builder
    {
        $query = $this->model
            ->with(['curso.area', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas', 'escuelaProgramada'])
            ->where('periodo_id', $periodoId)
            ->selectRaw('programacion_academica.*,
                (CASE WHEN lleno_manual = 1 OR n_inscritos >= capacidad THEN 1 ELSE 0 END) as esta_lleno_orden,
                (SELECT pe.tipo FROM plan_estudios pe
                 INNER JOIN planes_estudios plan ON plan.id = pe.plan_id AND plan.activo = 1
                 WHERE pe.curso_id = programacion_academica.curso_id
                   AND (
                     plan.escuela_id = programacion_academica.escuela_programada_id
                     OR (programacion_academica.escuela_programada_id IS NULL AND plan.escuela_id IN (
                       SELECT pesc.escuela_id FROM programacion_escuelas pesc
                       WHERE pesc.programacion_id = programacion_academica.id
                     ))
                   )
                 LIMIT 1) as tipo_plan')
            ->leftJoin('cursos', 'programacion_academica.curso_id', '=', 'cursos.id')
            ->orderByDesc('esta_lleno_orden')
            ->orderBy('cursos.nombre', 'asc');

        if ($escuelaId) {
            $query->where(function ($q) use ($escuelaId, $codigosEquivalentes) {
                // Cursos normales: asignados a la escuela del estudiante
                $q->whereExists(function ($sub) use ($escuelaId) {
                    $sub->from('programacion_escuelas')
                        ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                        ->where('programacion_escuelas.escuela_id', $escuelaId);
                });

                // Cursos equivalentes: mismo código, pero de OTRA escuela
                if (!empty($codigosEquivalentes)) {
                    $q->orWhere(function ($eq) use ($escuelaId, $codigosEquivalentes) {
                        $eq->whereIn('cursos.codigo', $codigosEquivalentes)
                            ->whereNotExists(function ($ne) use ($escuelaId) {
                                $ne->from('programacion_escuelas')
                                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                                    ->where('programacion_escuelas.escuela_id', $escuelaId);
                            });
                    });
                }
            });
        }

        if ($ciclo) {
            $query->where('programacion_academica.ciclo', $ciclo);
        }

        if ($areaId) {
            $query->where('cursos.area_id', $areaId);
        }

        if ($grupo) {
            $query->where('programacion_academica.grupo', strtoupper(trim($grupo)));
        }

        if ($escuelaProgramadaId) {
            $query->where('programacion_academica.escuela_programada_id', $escuelaProgramadaId);
        }

        if ($tipo) {
            $query->whereExists(function ($sub) use ($tipo) {
                $sub->from('plan_estudios as pe_f')
                    ->join('planes_estudios as plan_f', function ($join) {
                        $join->on('plan_f.id', '=', 'pe_f.plan_id')
                             ->where('plan_f.activo', 1);
                    })
                    ->whereColumn('pe_f.curso_id', 'programacion_academica.curso_id')
                    ->where('pe_f.tipo', $tipo)
                    ->where(function ($w) {
                        $w->whereColumn('plan_f.escuela_id', 'programacion_academica.escuela_programada_id')
                          ->orWhere(function ($fallback) {
                              $fallback->whereNull('programacion_academica.escuela_programada_id')
                                       ->whereExists(function ($pesc) {
                                           $pesc->from('programacion_escuelas')
                                                ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                                                ->whereColumn('programacion_escuelas.escuela_id', 'plan_f.escuela_id');
                                       });
                          });
                    });
            });
        }

        return $query;
    }

    public function getAllByPeriodo(string $periodoId, ?string $search = null, ?string $escuelaId = null, ?int $ciclo = null, ?string $areaId = null, ?string $grupo = null, ?string $escuelaProgramadaId = null): Collection
    {
        $query = $this->getBaseQuery($periodoId, $escuelaId, $ciclo, $areaId, $grupo, $escuelaProgramadaId);

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('clave', 'like', "%{$search}%")
                    ->orWhereHas('curso', fn($q) => $q->where('nombre', 'like', "%{$search}%")->orWhere('codigo', 'like', "%{$search}%"))
                    ->orWhereHas('docente', fn($q) => $q->where('nombre_completo', 'like', "%{$search}%"));
            });
        }

        return $query->get();
    }

    public function toggleLlenoManual(string $id): ?ProgramacionAcademica
    {
        $programacion = $this->model->find($id);

        if ($programacion) {
            $programacion->lleno_manual = !$programacion->lleno_manual;
            $programacion->save();
            $programacion->load(['curso.area', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario', 'escuelaProgramada']);
        }

        return $programacion;
    }
}
