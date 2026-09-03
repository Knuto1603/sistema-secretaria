<?php

namespace App\Repositories\Eloquent;

use App\Models\SolicitudAperturaCurso;
use App\Repositories\Contracts\SolicitudAperturaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SolicitudAperturaRepository implements SolicitudAperturaRepositoryInterface
{
    private const RELACIONES = [
        'user.escuela',
        'curso',
        'periodo',
        'escuela',
        'programacionReferencia.grupoHorario',
    ];

    public function __construct(
        protected SolicitudAperturaCurso $model
    ) {}

    public function create(array $data): SolicitudAperturaCurso
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): ?SolicitudAperturaCurso
    {
        $solicitud = $this->model->find($id);

        if ($solicitud) {
            $solicitud->update($data);
            $solicitud->load(self::RELACIONES);
        }

        return $solicitud;
    }

    public function findById(string $id): ?SolicitudAperturaCurso
    {
        return $this->model->with(self::RELACIONES)->find($id);
    }

    public function findByUserId(string $userId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model
            ->with(self::RELACIONES)
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
    }

    protected function getBaseQuery(): Builder
    {
        return $this->model->with(self::RELACIONES);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (!empty($filters['periodo_id'])) {
            $query->where('periodo_id', $filters['periodo_id']);
        }

        if (!empty($filters['curso_id'])) {
            $query->where('curso_id', $filters['curso_id']);
        }

        if (!empty($filters['escuela_id'])) {
            $query->where('escuela_id', $filters['escuela_id']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('codigo_universitario', 'like', "%{$search}%"))
                  ->orWhereHas('curso', fn($cq) => $cq->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->applyFilters($this->getBaseQuery(), $filters);

        return $query->latest()->paginate($perPage);
    }

    public function getAllForAgrupado(array $filters = []): Collection
    {
        $query = $this->applyFilters($this->getBaseQuery(), $filters);

        return $query->get();
    }

    public function existsSolicitudActivaParaCurso(string $userId, string $cursoId, string $periodoId): bool
    {
        return $this->model
            ->where('user_id', $userId)
            ->where('curso_id', $cursoId)
            ->where('periodo_id', $periodoId)
            ->whereIn('estado', ['pendiente', 'en_revision', 'aprobada'])
            ->exists();
    }
}
