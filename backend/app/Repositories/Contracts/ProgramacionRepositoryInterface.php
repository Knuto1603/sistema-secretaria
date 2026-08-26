<?php

namespace App\Repositories\Contracts;

use App\Models\ProgramacionAcademica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

interface ProgramacionRepositoryInterface
{
    public function getByPeriodoWithFilters(string $periodoId, ?string $search = null, int $perPage = 10): LengthAwarePaginator;

    public function findById(string $id): ?ProgramacionAcademica;

    public function deleteByPeriodo(string $periodoId): array;

    public function delete(string $id): bool;

    public function getBaseQuery(string $periodoId, ?string $escuelaId = null, ?int $ciclo = null, ?string $areaId = null, ?string $grupo = null, ?string $escuelaProgramadaId = null, array $codigosEquivalentes = [], ?string $tipo = null): Builder;

    public function getAllByPeriodo(string $periodoId, ?string $search = null, ?string $escuelaId = null, ?int $ciclo = null, ?string $areaId = null, ?string $grupo = null, ?string $escuelaProgramadaId = null): Collection;

    public function toggleLlenoManual(string $id): ?ProgramacionAcademica;

    public function create(array $data): ProgramacionAcademica;

    /** Marca lleno_manual=true en múltiples secciones (bulk, sin disparar observers). */
    public function cerrarMasivo(array $ids): int;

    /** Marca/desmarca lleno_manual en TODAS las secciones activas de un periodo. */
    public function marcarLlenoPorPeriodo(string $periodoId, bool $lleno): int;
}
