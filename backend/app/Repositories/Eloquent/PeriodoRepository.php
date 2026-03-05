<?php

namespace App\Repositories\Eloquent;

use App\Models\Periodo;
use App\Repositories\Contracts\PeriodoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PeriodoRepository implements PeriodoRepositoryInterface
{
    public function __construct(
        protected Periodo $model
    ) {}

    public function all(): Collection
    {
        return $this->model->orderBy('nombre', 'desc')->get();
    }

    public function find(string $id): ?Periodo
    {
        return $this->model->find($id);
    }

    public function findActive(): ?Periodo
    {
        return $this->model->where('activo', true)->first();
    }

    public function getActiveId(): ?string
    {
        return $this->model->where('activo', true)->value('id');
    }

    public function create(array $data): Periodo
    {
        return DB::transaction(function () use ($data) {
            // Si el nuevo periodo será activo, desactivar los demás
            if (!empty($data['activo'])) {
                $this->model->where('activo', true)->update(['activo' => false]);
            }

            return $this->model->create($data);
        });
    }

    public function update(string $id, array $data): ?Periodo
    {
        return DB::transaction(function () use ($id, $data) {
            $periodo = $this->find($id);

            if ($periodo) {
                // Si se está activando este periodo, desactivar los demás
                if (!empty($data['activo'])) {
                    $this->model->where('activo', true)
                        ->where('id', '!=', $id)
                        ->update(['activo' => false]);
                }

                $periodo->update($data);
            }

            return $periodo;
        });
    }

    public function delete(string $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    public function setActive(string $id): ?Periodo
    {
        return DB::transaction(function () use ($id) {
            // Deactivate all periods
            $this->model->where('activo', true)->update(['activo' => false]);

            // Activate the specified period
            $periodo = $this->find($id);

            if ($periodo) {
                $periodo->update(['activo' => true]);
            }

            return $periodo;
        });
    }

    public function deactivate(string $id): ?Periodo
    {
        $periodo = $this->find($id);

        if ($periodo) {
            $periodo->update(['activo' => false]);
        }

        return $periodo;
    }
}
