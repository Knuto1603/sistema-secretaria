<?php

namespace App\Repositories\Eloquent;

use App\Models\Curso;
use App\Repositories\Contracts\CursoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CursoRepository implements CursoRepositoryInterface
{
    public function __construct(
        protected Curso $model
    ) {}

    public function findById(string $id): ?Curso
    {
        return $this->model->with('area')->find($id);
    }

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->getBaseQuery();

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getBaseQuery(): Builder
    {
        return $this->model->select('cursos.*');
    }
}
