<?php

namespace App\Repositories\Eloquent;

use App\Models\TipoSolicitud;
use App\Repositories\Contracts\TipoSolicitudRepositoryInterface;
use Illuminate\Support\Collection;

class TipoSolicitudRepository implements TipoSolicitudRepositoryInterface
{
    public function __construct(
        protected TipoSolicitud $model
    ) {}

    public function all(): Collection
    {
        return $this->model->orderBy('nombre')->get();
    }

    public function findById(string $id): ?TipoSolicitud
    {
        return $this->model->find($id);
    }

    public function findByCode(string $codigo): ?TipoSolicitud
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    public function create(array $data): TipoSolicitud
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): ?TipoSolicitud
    {
        $tipo = $this->findById($id);

        if ($tipo) {
            $tipo->update($data);
        }

        return $tipo;
    }

    public function delete(string $id): bool
    {
        return $this->model->destroy($id) > 0;
    }
}
