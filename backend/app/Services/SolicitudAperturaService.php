<?php

namespace App\Services;

use App\DTOs\SolicitudApertura\CreateSolicitudAperturaDTO;
use App\Models\Curso;
use App\Models\Periodo;
use App\Models\Plan;
use App\Models\ProgramacionAcademica;
use App\Models\SolicitudAperturaCurso;
use App\Models\User;
use App\Repositories\Contracts\SolicitudAperturaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class SolicitudAperturaService
{
    public function __construct(
        protected SolicitudAperturaRepositoryInterface $repository,
        protected IndicadoresAperturaService $indicadores
    ) {}

    public function create(CreateSolicitudAperturaDTO $dto, User $user): SolicitudAperturaCurso
    {
        return DB::transaction(function () use ($dto, $user) {
            if (!$user->escuela_id) {
                throw new Exception('No tienes una escuela profesional asignada.');
            }

            $periodo = Periodo::where('activo', true)->first();
            if (!$periodo) {
                throw new Exception('No hay un periodo académico activo.');
            }

            if (!$periodo->solicitudes_abiertas) {
                throw new Exception('La presentación de solicitudes está cerrada. No se aceptan nuevas solicitudes en este momento.');
            }

            if ($dto->tipo === 'cambio_grupo') {
                $referencia = ProgramacionAcademica::find($dto->programacion_referencia_id);

                if (!$referencia || $referencia->curso_id !== $dto->curso_id) {
                    throw new Exception('La sección de referencia no corresponde al curso solicitado.');
                }
            }

            if ($this->repository->existsSolicitudActivaParaCurso($user->id, $dto->curso_id, $periodo->id)) {
                throw new Exception('Ya tienes una solicitud activa para este curso en el periodo actual. No puedes presentar otra hasta que sea resuelta.');
            }

            $firmaPath = $this->storeBase64Signature($dto->firma, $user->id);

            $solicitud = $this->repository->create([
                'user_id'                    => $user->id,
                'curso_id'                   => $dto->curso_id,
                'periodo_id'                 => $periodo->id,
                'escuela_id'                 => $user->escuela_id,
                'tipo'                       => $dto->tipo,
                'programacion_referencia_id' => $dto->tipo === 'cambio_grupo' ? $dto->programacion_referencia_id : null,
                'motivo'                     => $dto->motivo,
                'firma_digital_path'         => $firmaPath,
                'estado'                     => 'pendiente',
            ]);

            return $this->repository->findById($solicitud->id);
        });
    }

    public function getByUser(User $user, Request $request): LengthAwarePaginator
    {
        $perPage = $request->get('per_page', 10);
        return $this->repository->findByUserId($user->id, $perPage);
    }

    public function getAll(Request $request): LengthAwarePaginator
    {
        $filters = [
            'estado'     => $request->get('estado'),
            'search'     => $request->get('search'),
            'periodo_id' => $request->get('periodo_id'),
            'curso_id'   => $request->get('curso_id'),
            'escuela_id' => $request->get('escuela_id'),
            'tipo'       => $request->get('tipo'),
        ];

        $perPage = $request->get('per_page', 10);

        return $this->repository->getPaginated($filters, $perPage);
    }

    /**
     * Listado agrupado por curso con los indicadores de prioridad (vista principal admin).
     */
    public function getAgrupado(Request $request): array
    {
        $periodoId = $request->get('periodo_id') ?: Periodo::where('activo', true)->value('id');

        $filters = [
            'periodo_id' => $periodoId,
            'escuela_id' => $request->get('escuela_id'),
            'tipo'       => $request->get('tipo'),
        ];

        $solicitudes = $this->repository->getAllForAgrupado($filters);

        return $this->indicadores->calcularAgrupado($solicitudes);
    }

    /**
     * Busca en el catálogo completo de cursos (no solo los programados este periodo) y
     * anota, para cada uno, si ya tiene sección programada para la escuela del alumno
     * (sugiriendo 'cambio_grupo') o no (sugiriendo 'nueva_apertura'), su ciclo en el plan
     * del alumno y si ya presentó una solicitud activa para ese curso este periodo.
     */
    public function buscarCurso(User $user, Request $request): array
    {
        $search = $request->get('search');
        $perPage = (int) $request->get('per_page', 15);

        $query = Curso::query();
        if ($search) {
            $query->where(fn($q) => $q->where('nombre', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%"));
        }
        $paginator = $query->orderBy('codigo')->paginate($perPage);

        $cursoIds = collect($paginator->items())->pluck('id');
        $periodo = Periodo::where('activo', true)->first();

        $planCursosByCursoId = collect();
        if ($user->escuela_id) {
            $plan = Plan::where('escuela_id', $user->escuela_id)->where('activo', true)
                ->with('cursos')->first();
            $planCursosByCursoId = $plan ? $plan->cursos->keyBy('curso_id') : collect();
        }

        $seccionesPorCurso = collect();
        if ($periodo && $user->escuela_id) {
            $seccionesPorCurso = ProgramacionAcademica::periodo($periodo->id)
                ->whereIn('programacion_secciones.curso_id', $cursoIds)
                ->where('programacion_secciones.escuela_programada_id', $user->escuela_id)
                ->where('programacion_secciones.activo', true)
                ->select('programacion_secciones.*')
                ->with('grupoHorario')
                ->get()
                ->groupBy('curso_id');
        }

        $solicitudActivaCursoIds = $periodo
            ? $this->repository->getAllForAgrupado(['periodo_id' => $periodo->id])
                ->where('user_id', $user->id)
                ->whereIn('estado', ['pendiente', 'en_revision', 'aprobada'])
                ->pluck('curso_id')
                ->toArray()
            : [];

        $items = collect($paginator->items())->map(function ($curso) use ($seccionesPorCurso, $planCursosByCursoId, $solicitudActivaCursoIds) {
            $secciones = $seccionesPorCurso->get($curso->id, collect());
            $planCurso = $planCursosByCursoId->get($curso->id);

            return [
                'id'                        => $curso->id,
                'codigo'                    => $curso->codigo,
                'nombre'                    => $curso->nombre,
                'ciclo_en_mi_plan'          => $planCurso?->ciclo,
                'ya_tiene_solicitud_activa' => in_array($curso->id, $solicitudActivaCursoIds, true),
                'programado_este_periodo'   => $secciones->isNotEmpty(),
                'secciones'                 => $secciones->map(fn($s) => [
                    'id'      => $s->id,
                    'seccion' => $s->seccion,
                    'grupo'   => $s->grupoHorario?->nombre ?? $s->grupo,
                    'con_cupo' => !$s->estaLleno(),
                ])->values(),
                'tipo_sugerido' => $secciones->isNotEmpty() ? 'cambio_grupo' : 'nueva_apertura',
            ];
        })->values();

        return ['items' => $items, 'paginator' => $paginator];
    }

    public function findById(string $id): ?SolicitudAperturaCurso
    {
        return $this->repository->findById($id);
    }

    public function updateEstado(string $id, string $estado, ?string $observaciones = null): ?SolicitudAperturaCurso
    {
        $data = ['estado' => $estado];

        if ($observaciones !== null) {
            $data['observaciones_admin'] = $observaciones;
        }

        return $this->repository->update($id, $data);
    }

    public function updateEstadoMasivo(array $ids, string $estado, ?string $observaciones): int
    {
        return DB::transaction(function () use ($ids, $estado, $observaciones) {
            $actualizadas = 0;

            foreach ($ids as $id) {
                if ($this->updateEstado($id, $estado, $observaciones)) {
                    $actualizadas++;
                }
            }

            return $actualizadas;
        });
    }

    protected function storeBase64Signature(string $base64, string $userId): string
    {
        if (Str::contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }

        $decodedData = base64_decode($base64);
        $fileName = "firmas/apertura_signature_u{$userId}_" . now()->timestamp . ".png";

        Storage::disk('public')->put($fileName, $decodedData);

        return $fileName;
    }
}
