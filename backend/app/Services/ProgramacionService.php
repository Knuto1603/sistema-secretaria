<?php

namespace App\Services;

use App\DTOs\Programacion\ImportProgramacionDTO;
use App\DTOs\Programacion\ProgramacionFilterDTO;
use App\Imports\ProgramacionCampusImport;
use App\Imports\ProgramacionImport;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Plan;
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

    public function getPaginated(ProgramacionFilterDTO $dto, Request $request, ?User $user = null): LengthAwarePaginator
    {
        $periodoId = $dto->periodo_id ?? $this->periodoRepository->getActiveId();

        if (!$periodoId) {
            throw new Exception('No hay un periodo académico activo.');
        }

        // Para estudiantes, incluir cursos equivalentes de otras escuelas
        $codigosEquivalentes = [];
        if ($user && $user->isEstudiante() && $user->escuela_id) {
            $codigosEquivalentes = $this->getCodigosEquivalentes($periodoId, $user->escuela_id);
        }

        $query = $this->programacionRepository->getBaseQuery(
            $periodoId,
            $dto->escuela_id,
            $dto->ciclo,
            $dto->area_id,
            $dto->grupo,
            $dto->escuela_programada_id,
            $codigosEquivalentes,
            $dto->tipo
        );

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

    /**
     * Obtiene los códigos de cursos ofertados a la escuela del estudiante
     * que también tienen secciones en OTRAS escuelas (equivalentes).
     */
    private function getCodigosEquivalentes(string $periodoId, string $escuelaId): array
    {
        // Códigos de cursos asignados a la escuela del estudiante en este periodo
        $codigosEscuela = ProgramacionAcademica::where('periodo_id', $periodoId)
            ->whereExists(function ($q) use ($escuelaId) {
                $q->from('programacion_escuelas')
                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                    ->where('programacion_escuelas.escuela_id', $escuelaId);
            })
            ->join('cursos', 'cursos.id', '=', 'programacion_academica.curso_id')
            ->pluck('cursos.codigo')
            ->unique()
            ->values()
            ->toArray();

        if (empty($codigosEscuela)) {
            return [];
        }

        // De esos mismos códigos, verificar cuáles tienen secciones en OTRA escuela
        $codigosConOtraEscuela = ProgramacionAcademica::where('periodo_id', $periodoId)
            ->whereNotExists(function ($q) use ($escuelaId) {
                $q->from('programacion_escuelas')
                    ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                    ->where('programacion_escuelas.escuela_id', $escuelaId);
            })
            ->join('cursos as c2', 'c2.id', '=', 'programacion_academica.curso_id')
            ->whereIn('c2.codigo', $codigosEscuela)
            ->pluck('c2.codigo')
            ->unique()
            ->values()
            ->toArray();

        return $codigosConOtraEscuela;
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

    public function importCampus(ImportProgramacionDTO $dto): void
    {
        $periodoId = $dto->periodo_id ?? $this->periodoRepository->getActiveId();

        if (!$periodoId) {
            throw new Exception('No se pudo determinar el periodo académico.');
        }

        $this->programacionRepository->deleteByPeriodo($periodoId);
        Excel::import(new ProgramacionCampusImport($periodoId), $dto->file);
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

    public function deleteByPeriodo(string $periodoId): array
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

        // Usar plan activo si existe, fallback a escuela_id
        $planActivo = Plan::where('escuela_id', $user->escuela_id)->where('activo', true)->first();

        // Mostrar todos los ciclos del plan (sin límite de ciclo actual)
        $query = PlanEstudios::query();
        if ($planActivo) {
            $query->where('plan_id', $planActivo->id);
        } else {
            $query->where('escuela_id', $user->escuela_id);
        }
        $cursoIdsEnPlan = $query->pluck('curso_id')->toArray();

        if ($tieneHistorial) {
            $aprobadosIds = $user->cursosAprobados()->pluck('cursos.id')->toArray();

            // Expandir aprobados con equivalencias
            if (!empty($aprobadosIds)) {
                $equivalenciaIds = Curso::whereIn('id', $aprobadosIds)
                    ->with('equivalencias:id')
                    ->get()
                    ->flatMap(fn($c) => $c->equivalencias->pluck('id'))
                    ->toArray();
                $aprobadosIds = array_unique(array_merge($aprobadosIds, $equivalenciaIds));
            }

            $pendientesIds    = array_values(array_diff($cursoIdsEnPlan, $aprobadosIds));
            $pendientesCursos = Curso::whereIn('id', $pendientesIds)->with('requisitos')->get();

            $elegiblesIds = $pendientesCursos->filter(function (Curso $curso) use ($aprobadosIds) {
                if ($curso->requisitos->isEmpty()) return true;
                return $curso->requisitos->every(fn($req) => in_array($req->id, $aprobadosIds));
            })->pluck('id')->toArray();
        } else {
            $elegiblesIds = $cursoIdsEnPlan;
        }

        // IDs de programaciones en las que el alumno ya está inscrito este periodo
        $inscritosProgramacionIds = Inscripcion::where('user_id', $user->id)
            ->where('periodo_id', $periodoId)
            ->pluck('programacion_id')
            ->toArray();

        $cursoIdsFiltro = !empty($elegiblesIds) ? $elegiblesIds : ['__none__'];

        // Códigos de los cursos elegibles (para buscar equivalentes en otras escuelas)
        $codigosElegibles = !empty($elegiblesIds)
            ? Curso::whereIn('id', $elegiblesIds)->pluck('codigo')->toArray()
            : [];

        // Llamar sin filtro de escuela para poder hacer el OR correctamente;
        // el filtro de escuela se aplica dentro del where() para que los
        // cursos en los que el alumno YA está inscrito no sean excluidos.
        $query = $this->programacionRepository
            ->getBaseQuery($periodoId)
            ->where(function ($q) use ($cursoIdsFiltro, $codigosElegibles, $inscritosProgramacionIds, $user) {
                // Rama 1: cursos elegibles habilitados para la escuela del alumno
                $q->where(function ($inner) use ($cursoIdsFiltro, $user) {
                    $inner->whereIn('programacion_academica.curso_id', $cursoIdsFiltro)
                          ->whereExists(function ($sub) use ($user) {
                              $sub->from('programacion_escuelas')
                                  ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                                  ->where('programacion_escuelas.escuela_id', $user->escuela_id);
                          });
                });
                // Rama 2: mismo código de curso programado en otra escuela
                if (!empty($codigosElegibles)) {
                    $q->orWhere(function ($inner) use ($codigosElegibles, $user) {
                        $inner->whereIn('cursos.codigo', $codigosElegibles)
                              ->whereNotExists(function ($sub) use ($user) {
                                  $sub->from('programacion_escuelas')
                                      ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                                      ->where('programacion_escuelas.escuela_id', $user->escuela_id);
                              });
                    });
                }
                // Rama 3: cursos en los que ya está inscrito este periodo
                if (!empty($inscritosProgramacionIds)) {
                    $q->orWhereIn('programacion_academica.id', $inscritosProgramacionIds);
                }
            });

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
