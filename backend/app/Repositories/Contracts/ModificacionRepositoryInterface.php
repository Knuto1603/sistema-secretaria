<?php

namespace App\Repositories\Contracts;

use App\DTOs\Modificacion\ModificacionFilterDTO;
use App\Models\ModificacionProgramacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ModificacionRepositoryInterface
{
    public function paginate(ModificacionFilterDTO $filters): LengthAwarePaginator;

    public function findById(string $id): ?ModificacionProgramacion;

    public function create(array $data): ModificacionProgramacion;

    /** Devuelve modificaciones pendientes en un rango de fechas, agrupadas por área y tipo */
    public function getPendientesPorAreaYTipo(string $periodoId, string $fechaDesde, string $fechaHasta): Collection;

    /** Marca como 'documentado' un conjunto de IDs */
    public function marcarDocumentados(array $ids): int;
}
