<?php

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
            ->with(['curso.area', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas'])
            ->find($id);
    }

    public function deleteByPeriodo(string $periodoId): int
    {
        return $this->model->where('periodo_id', $periodoId)->delete();
    }

    public function delete(string $id): bool
    {
        $prog = $this->model->find($id);
        if (!$prog) return false;
        return (bool) $prog->delete();
    }

    public function getBaseQuery(string $periodoId, ?string $escuelaId = null, ?int $ciclo = null): Builder
    {
        $query = $this->model
            ->with(['curso.area', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario', 'escuelas'])
            ->where('periodo_id', $periodoId)
            ->selectRaw('programacion_academica.*,
                (CASE WHEN lleno_manual = 1 OR n_inscritos >= capacidad THEN 1 ELSE 0 END) as esta_lleno_orden')
            ->leftJoin('cursos', 'programacion_academica.curso_id', '=', 'cursos.id')
            ->orderByDesc('esta_lleno_orden')
            ->orderBy('cursos.nombre', 'asc');

        if ($escuelaId) {
            $query->whereExists(function ($sub) use ($escuelaId) {
                $sub->from('programacion_escuelas')
                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                    ->where('programacion_escuelas.escuela_id', $escuelaId);
            });
        }

        if ($ciclo) {
            $query->where('programacion_academica.ciclo', $ciclo);
        }

        return $query;
    }

    public function getAllByPeriodo(string $periodoId, ?string $search = null, ?string $escuelaId = null, ?int $ciclo = null): Collection
    {
        $query = $this->getBaseQuery($periodoId, $escuelaId, $ciclo);

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
            $programacion->load(['curso.area', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario']);
        }

        return $programacion;
    }
}
