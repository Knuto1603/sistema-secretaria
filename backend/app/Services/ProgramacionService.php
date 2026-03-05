<?php

namespace App\Services;

use App\DTOs\Programacion\ImportProgramacionDTO;
use App\DTOs\Programacion\ProgramacionFilterDTO;
use App\Imports\ProgramacionImport;
use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\PeriodoRepositoryInterface;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use App\Traits\ApiFilterable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class ProgramacionService
{
    use ApiFilterable;

    public function __construct(
        protected ProgramacionRepositoryInterface $programacionRepository,
        protected PeriodoRepositoryInterface $periodoRepository
    ) {}

    public function getPaginated(ProgramacionFilterDTO $dto, Request $request): LengthAwarePaginator
    {
        $periodoId = $dto->periodo_id ?? $this->periodoRepository->getActiveId();

        if (!$periodoId) {
            throw new Exception('No hay un periodo académico activo.');
        }

        $query = $this->programacionRepository->getBaseQuery($periodoId, $dto->escuela_id);

        return $this->applyFiltersAndPaginate(
            $query,
            $request,
            ['clave', 'grupo'],
            [
                'curso' => ['nombre', 'codigo'],
                'docente' => ['nombre_completo']
            ],
            false // No aplicar ordenamiento por defecto, ya está ordenado en la query
        );
    }

    public function import(ImportProgramacionDTO $dto): void
    {
        $periodoId = $dto->periodo_id ?? $this->periodoRepository->getActiveId();

        if (!$periodoId) {
            throw new Exception('No se pudo determinar el periodo académico.');
        }

        $this->programacionRepository->deleteByPeriodo($periodoId);
        Excel::import(new ProgramacionImport($periodoId), $dto->file);
    }

    public function findById(string $id): ?ProgramacionAcademica
    {
        return $this->programacionRepository->findById($id);
    }

    public function getActivePeriodoId(): ?string
    {
        return $this->periodoRepository->getActiveId();
    }

    public function toggleLlenoManual(string $id): ?ProgramacionAcademica
    {
        return $this->programacionRepository->toggleLlenoManual($id);
    }
}
