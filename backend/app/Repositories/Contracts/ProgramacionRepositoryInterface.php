<?php

namespace App\Repositories\Contracts;

use App\Models\ProgramacionAcademica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface ProgramacionRepositoryInterface
{
    public function getByPeriodoWithFilters(string $periodoId, ?string $search = null, int $perPage = 10): LengthAwarePaginator;

    public function findById(string $id): ?ProgramacionAcademica;

    public function deleteByPeriodo(string $periodoId): int;

    public function getBaseQuery(string $periodoId, ?string $escuelaId = null): Builder;

    public function toggleLlenoManual(string $id): ?ProgramacionAcademica;
}
