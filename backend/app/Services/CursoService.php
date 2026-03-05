<?php

namespace App\Services;

use App\Models\Curso;
use App\Repositories\Contracts\CursoRepositoryInterface;
use App\Traits\ApiFilterable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CursoService
{
    use ApiFilterable;

    public function __construct(
        protected CursoRepositoryInterface $repository
    ) {}

    public function getPaginated(Request $request): LengthAwarePaginator
    {
        $query = $this->repository->getBaseQuery();

        return $this->applyFiltersAndPaginate(
            $query,
            $request,
            ['nombre', 'codigo']
        );
    }

    public function findById(string $id): ?Curso
    {
        return $this->repository->findById($id);
    }
}
