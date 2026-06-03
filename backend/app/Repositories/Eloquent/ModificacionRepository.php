<?php

namespace App\Repositories\Eloquent;

use App\DTOs\Modificacion\ModificacionFilterDTO;
use App\Models\ModificacionProgramacion;
use App\Repositories\Contracts\ModificacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ModificacionRepository implements ModificacionRepositoryInterface
{
    public function __construct(
        protected ModificacionProgramacion $model
    ) {}

    public function paginate(ModificacionFilterDTO $filters): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with([
                'periodo:id,nombre',
                'user:id,name',
                'programacion.curso.area:id,nombre,nombre_tabla',
                'programacion.aulaRelacion:id,nombre',
                'programacion.grupoHorario:id,nombre',
            ]);

        if ($filters->periodo_id) {
            $query->where('periodo_id', $filters->periodo_id);
        }

        if ($filters->tipo) {
            $query->where('tipo', $filters->tipo);
        }

        if ($filters->estado) {
            $query->where('estado', $filters->estado);
        }

        if ($filters->area_id) {
            $query->whereHas('programacion.curso', fn ($q) => $q->where('area_id', $filters->area_id));
        }

        if ($filters->fecha_desde) {
            $query->whereDate('created_at', '>=', $filters->fecha_desde);
        }

        if ($filters->fecha_hasta) {
            $query->whereDate('created_at', '<=', $filters->fecha_hasta);
        }

        if ($filters->search) {
            $query->whereHas('programacion.curso', function ($q) use ($filters) {
                $q->where('nombre', 'like', "%{$filters->search}%")
                  ->orWhere('codigo', 'like', "%{$filters->search}%");
            });
        }

        return $query->latest()->paginate($filters->per_page);
    }

    public function findById(string $id): ?ModificacionProgramacion
    {
        return $this->model->with([
            'periodo:id,nombre',
            'user:id,name',
            'programacion.curso.area',
            'programacion.aulaRelacion',
            'programacion.grupoHorario',
        ])->find($id);
    }

    public function create(array $data): ModificacionProgramacion
    {
        return $this->model->create($data);
    }

    public function getPendientesPorAreaYTipo(string $periodoId, string $fechaDesde, string $fechaHasta): Collection
    {
        return $this->model->newQuery()
            ->with([
                'programacion.curso.area:id,nombre,nombre_tabla,director_nombre,director_cargo,titulo_director',
                'programacion.aulaRelacion:id,nombre',
                'programacion.grupoHorario:id,nombre',
                'user:id,name',
            ])
            ->where('periodo_id', $periodoId)
            ->where('estado', 'pendiente')
            ->whereDate('created_at', '>=', $fechaDesde)
            ->whereDate('created_at', '<=', $fechaHasta)
            ->orderBy('created_at')
            ->get();
    }

    public function getPendientesPorPeriodo(string $periodoId): Collection
    {
        return $this->model->newQuery()
            ->with([
                'programacion.curso.area:id,nombre,nombre_tabla,director_nombre,director_cargo,titulo_director',
                'programacion.aulaRelacion:id,nombre',
                'programacion.grupoHorario:id,nombre',
                'user:id,name',
            ])
            ->where('periodo_id', $periodoId)
            ->where('estado', 'pendiente')
            ->orderBy('created_at')
            ->get();
    }

    public function getPendientesPorIds(array $ids, string $periodoId): Collection
    {
        return $this->model->newQuery()
            ->with([
                'programacion.curso.area:id,nombre,nombre_tabla,director_nombre,director_cargo,titulo_director',
                'programacion.aulaRelacion:id,nombre',
                'programacion.grupoHorario:id,nombre',
                'user:id,name',
            ])
            ->whereIn('id', $ids)
            ->where('periodo_id', $periodoId)
            ->where('estado', 'pendiente')
            ->orderBy('created_at')
            ->get();
    }

    public function marcarDocumentados(array $ids): int
    {
        return $this->model->whereIn('id', $ids)->update(['estado' => 'documentado']);
    }
}
