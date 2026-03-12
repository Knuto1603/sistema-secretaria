<?php

namespace App\Services;

use App\DTOs\Programacion\ImportProgramacionDTO;
use App\DTOs\Programacion\ProgramacionFilterDTO;
use App\Imports\ProgramacionImport;
use App\Models\Curso;
use App\Models\PlanEstudios;
use App\Models\ProgramacionAcademica;
use App\Models\User;
use App\Repositories\Contracts\PeriodoRepositoryInterface;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use App\Traits\ApiFilterable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
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

        $query = $this->programacionRepository->getBaseQuery($periodoId, $dto->escuela_id, $dto->ciclo, $dto->area_id, $dto->grupo, $dto->escuela_programada_id);

        return $this->applyFiltersAndPaginate(
            $query,
            $request,
            ['clave', 'grupo'],
            [
                'curso'   => ['nombre', 'codigo'],
                'docente' => ['nombre_completo']
            ],
            false
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

    public function delete(string $id): bool
    {
        return $this->programacionRepository->delete($id);
    }

    public function deleteByPeriodo(string $periodoId): int
    {
        return $this->programacionRepository->deleteByPeriodo($periodoId);
    }

    /**
     * Obtiene todas las programaciones para exportación (sin paginar)
     */
    public function getAllForExport(string $periodoId, ?string $search = null, ?string $escuelaId = null, ?int $ciclo = null, ?string $areaId = null, ?string $escuelaProgramadaId = null): Collection
    {
        return $this->programacionRepository->getAllByPeriodo($periodoId, $search, $escuelaId, $ciclo, $areaId, null, $escuelaProgramadaId);
    }

    /**
     * Devuelve las programaciones elegibles para un estudiante.
     */
    public function getElegiblesParaEstudiante(User $user, Request $request): array
    {
        $periodoId = $this->periodoRepository->getActiveId();

        if (!$periodoId) {
            throw new Exception('No hay un periodo académico activo.');
        }

        if (!$user->escuela_id) {
            throw new Exception('Tu cuenta no tiene una escuela asignada. Contacta a secretaría.');
        }

        $cicloActual    = $user->cicloActual();
        $tieneHistorial = $user->tieneHistorial();

        $cursoIdsEnPlan = PlanEstudios::where('escuela_id', $user->escuela_id)
            ->where('ciclo', '<=', $cicloActual)
            ->pluck('curso_id')
            ->toArray();

        if ($tieneHistorial) {
            $aprobadosIds   = $user->cursosAprobados()->pluck('cursos.id')->toArray();
            $pendientesIds  = array_values(array_diff($cursoIdsEnPlan, $aprobadosIds));
            $pendientesCursos = Curso::whereIn('id', $pendientesIds)->with('requisitos')->get();

            $elegiblesIds = $pendientesCursos->filter(function (Curso $curso) use ($aprobadosIds) {
                if ($curso->requisitos->isEmpty()) return true;
                return $curso->requisitos->every(fn($req) => in_array($req->id, $aprobadosIds));
            })->pluck('id')->toArray();
        } else {
            $elegiblesIds = $cursoIdsEnPlan;
        }

        $cursoIdsFiltro = !empty($elegiblesIds) ? $elegiblesIds : ['__none__'];

        $query = $this->programacionRepository
            ->getBaseQuery($periodoId, $user->escuela_id)
            ->whereIn('programacion_academica.curso_id', $cursoIdsFiltro);

        $paginated = $this->applyFiltersAndPaginate(
            $query,
            $request,
            ['clave', 'grupo'],
            [
                'curso'   => ['nombre', 'codigo'],
                'docente' => ['nombre_completo'],
            ],
            false
        );

        return [
            'ciclo_actual'        => $cicloActual,
            'historial_registrado' => $tieneHistorial,
            'paginated'           => $paginated,
        ];
    }
}
