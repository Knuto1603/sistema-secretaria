<?php

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Models\ProgramacionSeccion;
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
                $q->where('programacion_secciones.clave', 'like', "%{$search}%")
                    ->orWhere('programacion_secciones.grupo', 'like', "%{$search}%")
                    ->orWhereHas('curso', function (Builder $q) use ($search) {
                        $q->where('nombre', 'like', "%{$search}%")
                            ->orWhere('codigo', 'like', "%{$search}%");
                    })
                    ->orWhereHas('docente', function (Builder $q) use ($search) {
                        $q->where('nombre_completo', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest('programacion_secciones.created_at')->paginate($perPage);
    }

    public function findById(string $id): ?ProgramacionAcademica
    {
        return $this->model
            ->with(['curso.area', 'docente', 'programacion.periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas', 'escuelaProgramada'])
            ->find($id);
    }

    public function deleteByPeriodo(string $periodoId): array
    {
        return DB::transaction(function () use ($periodoId) {
            $ids = ProgramacionSeccion::whereHas('programacion', fn($q) => $q->where('periodo_id', $periodoId))
                ->pluck('programacion_secciones.id');

            if ($ids->isEmpty()) {
                return ['programacion' => 0, 'inscripciones' => 0, 'solicitudes' => 0];
            }

            $solicitudes   = DB::table('solicitud')->whereIn('programacion_id', $ids)->delete();
            $inscripciones = DB::table('inscripciones')->whereIn('programacion_id', $ids)->delete();
            DB::table('programacion_escuelas')->whereIn('programacion_id', $ids)->delete();

            $programacion = ProgramacionSeccion::whereIn('id', $ids)->delete();

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
            ->with(['curso.area', 'docente', 'programacion.periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas', 'escuelaProgramada'])
            ->join('programaciones', 'programaciones.id', '=', 'programacion_secciones.programacion_id')
            ->join('cursos', 'programacion_secciones.curso_id', '=', 'cursos.id')
            ->where('programaciones.periodo_id', $periodoId)
            ->where('programaciones.estado', 'publicado')
            ->selectRaw('programacion_secciones.*,
                (CASE WHEN programacion_secciones.lleno_manual = 1 OR programacion_secciones.n_inscritos >= programacion_secciones.capacidad THEN 1 ELSE 0 END) as esta_lleno_orden,
                (SELECT pe.tipo FROM plan_estudios pe
                 WHERE pe.curso_id = programacion_secciones.curso_id
                   AND (
                     pe.escuela_id = programacion_secciones.escuela_programada_id
                     OR (
                       programacion_secciones.escuela_programada_id IS NULL
                       AND (pe.escuela_id = ? OR EXISTS (
                         SELECT 1 FROM programacion_escuelas pesc
                         WHERE pesc.programacion_id = programacion_secciones.id
                           AND pesc.escuela_id = pe.escuela_id
                       ))
                     )
                   )
                 ORDER BY (pe.escuela_id = ?) DESC
                 LIMIT 1) as tipo_plan', [$escuelaId, $escuelaId])
            ->orderByDesc('esta_lleno_orden')
            ->orderBy('cursos.nombre', 'asc');

        if ($escuelaId) {
            $query->where(function ($q) use ($escuelaId, $codigosEquivalentes) {
                $q->whereExists(function ($sub) use ($escuelaId) {
                    $sub->from('programacion_escuelas')
                        ->whereColumn('programacion_escuelas.programacion_id', 'programacion_secciones.id')
                        ->where('programacion_escuelas.escuela_id', $escuelaId);
                });

                if (!empty($codigosEquivalentes)) {
                    $q->orWhere(function ($eq) use ($escuelaId, $codigosEquivalentes) {
                        $eq->whereIn('cursos.codigo', $codigosEquivalentes)
                            ->whereNotExists(function ($ne) use ($escuelaId) {
                                $ne->from('programacion_escuelas')
                                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_secciones.id')
                                    ->where('programacion_escuelas.escuela_id', $escuelaId);
                            });
                    });
                }
            });
        }

        if ($ciclo) {
            $query->where('programacion_secciones.ciclo', $ciclo);
        }

        if ($areaId) {
            $query->where('cursos.area_id', $areaId);
        }

        if ($grupo) {
            $query->where('programacion_secciones.grupo', strtoupper(trim($grupo)));
        }

        if ($escuelaProgramadaId) {
            $query->where('programacion_secciones.escuela_programada_id', $escuelaProgramadaId);
        }

        if ($tipo) {
            $query->whereRaw('EXISTS (
                SELECT 1 FROM plan_estudios pe_f
                WHERE pe_f.curso_id = programacion_secciones.curso_id
                  AND pe_f.tipo = ?
                  AND (
                    pe_f.escuela_id = programacion_secciones.escuela_programada_id
                    OR (
                      programacion_secciones.escuela_programada_id IS NULL
                      AND EXISTS (
                        SELECT 1 FROM programacion_escuelas pesc_f
                        WHERE pesc_f.programacion_id = programacion_secciones.id
                          AND pesc_f.escuela_id = pe_f.escuela_id
                      )
                    )
                  )
            )', [$tipo]);
        }

        $query->where('programacion_secciones.activo', true);

        return $query;
    }

    public function getAllByPeriodo(string $periodoId, ?string $search = null, ?string $escuelaId = null, ?int $ciclo = null, ?string $areaId = null, ?string $grupo = null, ?string $escuelaProgramadaId = null): Collection
    {
        $query = $this->getBaseQuery($periodoId, $escuelaId, $ciclo, $areaId, $grupo, $escuelaProgramadaId);

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('programacion_secciones.clave', 'like', "%{$search}%")
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
            $programacion->load(['curso.area', 'docente', 'programacion.periodo', 'aulaRelacion.pabellon', 'grupoHorario', 'escuelaProgramada']);
        }

        return $programacion;
    }

    public function create(array $data): ProgramacionAcademica
    {
        return $this->model->create($data);
    }

    public function cerrarMasivo(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->update(['lleno_manual' => true]);
    }
}
