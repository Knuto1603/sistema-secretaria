<?php

namespace App\Repositories\Contracts;

use App\Models\Curso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface CursoRepositoryInterface
{
    public function findById(string $id): ?Curso;

    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator;

    public function getBaseQuery(): Builder;
}
