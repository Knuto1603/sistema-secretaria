<?php

namespace App\Repositories\Eloquent;

use App\Models\Solicitud;
use App\Repositories\Contracts\SolicitudRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SolicitudRepository implements SolicitudRepositoryInterface
{
    public function __construct(
        protected Solicitud $model
    ) {}

    public function create(array $data): Solicitud
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): ?Solicitud
    {
        $solicitud = $this->model->find($id);

        if ($solicitud) {
            $solicitud->update($data);
            $solicitud->load(['user.escuela', 'tipoSolicitud', 'programacion.curso', 'programacion.docente', 'programacion.aulaRelacion', 'programacion.escuelaProgramada', 'periodo']);
        }

        return $solicitud;
    }

    public function findById(string $id): ?Solicitud
    {
        return $this->model
            ->with(['user.escuela', 'tipoSolicitud', 'programacion.curso', 'programacion.docente', 'programacion.aulaRelacion', 'programacion.escuelaProgramada', 'periodo', 'asignado'])
            ->find($id);
    }

    public function findByUserId(string $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model
            ->with(['tipoSolicitud', 'programacion.curso', 'programacion.docente', 'programacion.aulaRelacion', 'programacion.escuelaProgramada', 'periodo'])
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    public function getBaseQuery(): Builder
    {
        return $this->model
            ->with(['user.escuela', 'tipoSolicitud', 'programacion.curso', 'programacion.docente', 'programacion.aulaRelacion', 'programacion.escuelaProgramada', 'periodo']);
    }

    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->getBaseQuery();

        if (isset($filters['estado']) && $filters['estado']) {
            $query->where('estado', $filters['estado']);
        }

        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                       ->orWhere('codigo_universitario', 'like', "%{$search}%");
                })
                ->orWhereHas('programacion.curso', function ($cq) use ($search) {
                    $cq->where('nombre', 'like', "%{$search}%")
                       ->orWhere('codigo', 'like', "%{$search}%");
                });
            });
        }

        if (isset($filters['programacion_id']) && $filters['programacion_id']) {
            $query->where('programacion_id', $filters['programacion_id']);
        }

        if (isset($filters['periodo_id']) && $filters['periodo_id']) {
            $query->where('periodo_id', $filters['periodo_id']);
        }

        if (isset($filters['curso_id']) && $filters['curso_id']) {
            $query->whereHas('programacion', fn($q) => $q->where('curso_id', $filters['curso_id']));
        }

        if (isset($filters['grupo']) && $filters['grupo']) {
            $query->whereHas('programacion', fn($q) => $q->where('grupo', $filters['grupo']));
        }

        if (isset($filters['tipo']) && $filters['tipo']) {
            $query->whereHas('tipoSolicitud', fn($q) => $q->where('codigo', $filters['tipo']));
        }

        if (isset($filters['escuela_id']) && $filters['escuela_id']) {
            $query->whereHas('user', fn($q) => $q->where('escuela_id', $filters['escuela_id']));
        }

        if (isset($filters['escuela_programada_id']) && $filters['escuela_programada_id']) {
            $query->whereHas('programacion', fn($q) => $q->where('escuela_programada_id', $filters['escuela_programada_id']));
        }

        $order = ($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        return $query->orderBy('solicitud.created_at', $order)->paginate($perPage);
    }

    public function getAllForExport(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->getBaseQuery();

        if (isset($filters['estado']) && $filters['estado']) {
            $query->where('estado', $filters['estado']);
        }
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('codigo_universitario', 'like', "%{$search}%"))
                  ->orWhereHas('programacion.curso', fn($cq) => $cq->where('nombre', 'like', "%{$search}%")->orWhere('codigo', 'like', "%{$search}%"));
            });
        }
        if (isset($filters['programacion_id']) && $filters['programacion_id']) {
            $query->where('programacion_id', $filters['programacion_id']);
        }
        if (isset($filters['periodo_id']) && $filters['periodo_id']) {
            $query->where('periodo_id', $filters['periodo_id']);
        }
        if (isset($filters['curso_id']) && $filters['curso_id']) {
            $query->whereHas('programacion', fn($q) => $q->where('curso_id', $filters['curso_id']));
        }
        if (isset($filters['grupo']) && $filters['grupo']) {
            $query->whereHas('programacion', fn($q) => $q->where('grupo', $filters['grupo']));
        }
        if (isset($filters['tipo']) && $filters['tipo']) {
            $query->whereHas('tipoSolicitud', fn($q) => $q->where('codigo', $filters['tipo']));
        }
        if (isset($filters['escuela_id']) && $filters['escuela_id']) {
            $query->whereHas('user', fn($q) => $q->where('escuela_id', $filters['escuela_id']));
        }
        if (isset($filters['escuela_programada_id']) && $filters['escuela_programada_id']) {
            $query->whereHas('programacion', fn($q) => $q->where('escuela_programada_id', $filters['escuela_programada_id']));
        }

        return $query->latest()->get();
    }

    /**
     * Verifica si existe una solicitud activa (no rechazada) del usuario para el mismo curso
     * dentro del mismo periodo académico. Busca por código de curso, sin importar el grupo.
     */
    public function existsSolicitudActivaParaCurso(string $userId, string $cursoId, string $periodoId): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('periodo_id', $periodoId)
            ->whereNotIn('estado', ['rechazada']) // Solo excluimos rechazadas, el resto son activas
            ->whereHas('programacion.curso', function ($query) use ($cursoId) {
                $query->where('id', $cursoId);
            })
            ->exists();
    }
}
