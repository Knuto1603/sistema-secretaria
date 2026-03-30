<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Solicitud\CreateSolicitudDTO;
use App\Exports\SolicitudesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitud\CreateSolicitudRequest;
use App\Models\ProgramacionAcademica;
use App\Models\Solicitud;
use App\Services\SolicitudService;
use App\Transformers\SolicitudTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Exception;

class SolicitudController extends Controller
{
    public function __construct(
        protected SolicitudService $service,
        protected SolicitudTransformer $transformer
    ) {}

    /**
     * Crear nueva solicitud (estudiantes)
     */
    public function store(CreateSolicitudRequest $request): JsonResponse
    {
        try {
            $dto = CreateSolicitudDTO::fromRequest(
                $request->validated(),
                $request->file('archivo_sustento'),
                $request->userAgent(),
                $request->ip()
            );

            $solicitud = $this->service->create($dto, $request->user());

            return $this->created(
                $this->transformer->toArray($solicitud),
                'Solicitud enviada exitosamente'
            );
        } catch (Exception $e) {
            // Errores de validación de negocio retornan 422
            $businessErrors = [
                'Ya tienes una solicitud',
                'periodos académicos inactivos',
                'no existe',
                'no tiene un curso',
                'no pertenece al plan de estudios',
            ];
            $isBusinessError = collect($businessErrors)->contains(fn($msg) => str_contains($e->getMessage(), $msg));
            $code = $isBusinessError ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Listar solicitudes del usuario autenticado (estudiantes)
     */
    public function misSolicitudes(Request $request): JsonResponse
    {
        $result = $this->service->getByUser($request->user(), $request);
        $items = $this->transformer->collection(collect($result->items()));

        return $this->paginated($items, $result, 'Mis solicitudes');
    }

    /**
     * Listar todas las solicitudes (admin/secretaria/decano)
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->getAll($request);
        $items = $this->transformer->collection(collect($result->items()));

        return $this->paginated($items, $result, 'Lista de solicitudes');
    }

    /**
     * Ver detalle de una solicitud
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $solicitud = $this->service->findById($id);

        if (!$solicitud) {
            return $this->notFound('Solicitud no encontrada');
        }

        // Verificar que el estudiante solo pueda ver sus propias solicitudes
        $user = $request->user();
        if ($user->hasRole('estudiante') && $solicitud->user_id !== $user->id) {
            return $this->forbidden('No tienes permiso para ver esta solicitud');
        }

        return $this->success($this->transformer->toArray($solicitud));
    }

    /**
     * Anular solicitud (solo el propio estudiante, solo si está pendiente o en_revision)
     */
    public function anular(string $id, Request $request): JsonResponse
    {
        $solicitud = $this->service->findById($id);

        if (!$solicitud) {
            return $this->notFound('Solicitud no encontrada');
        }

        $user = $request->user();

        if ($solicitud->user_id !== $user->id) {
            return $this->forbidden('No puedes anular una solicitud que no es tuya');
        }

        if (!in_array($solicitud->estado, ['pendiente', 'en_revision'])) {
            return $this->error('Solo puedes anular solicitudes pendientes o en revisión', 422);
        }

        $solicitud->delete();

        return $this->success(null, 'Solicitud anulada correctamente');
    }

    /**
     * Devuelve los programacion_ids donde el estudiante ya tiene solicitud activa
     * (pendiente, en_revision o aprobada). Usado para deshabilitar el botón "Solicitar".
     */
    public function programacionesActivas(Request $request): JsonResponse
    {
        $ids = Solicitud::where('user_id', $request->user()->id)
            ->whereNotNull('programacion_id')
            ->whereIn('estado', ['pendiente', 'en_revision', 'aprobada'])
            ->pluck('programacion_id')
            ->unique()
            ->values();

        return $this->success($ids, 'Programaciones con solicitud activa');
    }

    /**
     * Cursos distintos que tienen al menos una solicitud (para el filtro del listado admin)
     */
    public function cursosConSolicitud(): JsonResponse
    {
        $items = Solicitud::with(['programacion.curso', 'programacion.escuelaProgramada'])
            ->whereNotNull('programacion_id')
            ->get()
            ->unique('programacion_id')
            ->values()
            ->filter(fn($s) => $s->programacion?->curso)
            ->map(fn($s) => [
                'id'      => $s->programacion_id,
                'clave'   => $s->programacion->clave,
                'grupo'   => $s->programacion->grupo,
                'seccion' => $s->programacion->seccion,
                'curso'   => [
                    'nombre' => $s->programacion->curso->nombre,
                    'codigo' => $s->programacion->curso->codigo,
                ],
                'escuela_programada' => $s->programacion->escuelaProgramada?->nombre_corto
                                     ?? $s->programacion->escuelaProgramada?->nombre,
            ])
            ->values();

        return $this->success($items);
    }

    /**
     * Exportar solicitudes filtradas como Excel
     */
    public function exportar(Request $request)
    {
        $solicitudes = $this->service->getAllForExport($request);
        $filename = 'solicitudes_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new SolicitudesExport($solicitudes), $filename);
    }

    /**
     * Estadísticas de solicitudes de cupo (para secretaría/admin).
     * Devuelve conteos por estado y los cursos más solicitados.
     */
    public function estadisticas(): JsonResponse
    {
        $porEstado = Solicitud::selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $porTipo = Solicitud::join('tipo_solicitudes', 'solicitud.tipo_solicitud_id', '=', 'tipo_solicitudes.id')
            ->selectRaw('tipo_solicitudes.codigo, tipo_solicitudes.nombre, count(*) as total')
            ->groupBy('tipo_solicitudes.codigo', 'tipo_solicitudes.nombre')
            ->pluck('total', 'tipo_solicitudes.codigo');

        // Cursos más solicitados agrupados por curso (no por sección)
        $cursosTop = Solicitud::with(['programacion.curso', 'programacion.escuelaProgramada', 'tipoSolicitud'])
            ->whereNotNull('programacion_id')
            ->join('programacion_academica', 'solicitud.programacion_id', '=', 'programacion_academica.id')
            ->join('cursos', 'cursos.id', '=', 'programacion_academica.curso_id')
            ->selectRaw('cursos.id as curso_id, cursos.codigo, cursos.nombre as curso_nombre, programacion_academica.escuela_programada_id, count(*) as total_solicitudes')
            ->groupBy('cursos.id', 'cursos.codigo', 'cursos.nombre', 'programacion_academica.escuela_programada_id')
            ->orderByDesc('total_solicitudes')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'curso'             => $s->curso_nombre,
                'codigo'            => $s->codigo,
                'total_solicitudes' => (int) $s->total_solicitudes,
                'escuela_programada'=> $s->programacion?->escuelaProgramada?->nombre_corto
                                      ?? $s->programacion?->escuelaProgramada?->nombre ?? null,
            ]);

        // Solicitudes de cupo extra agrupadas por escuela del alumno
        $porEscuela = Solicitud::join('users', 'solicitud.user_id', '=', 'users.id')
            ->join('escuelas', 'users.escuela_id', '=', 'escuelas.id')
            ->join('tipo_solicitudes', 'solicitud.tipo_solicitud_id', '=', 'tipo_solicitudes.id')
            ->where('tipo_solicitudes.codigo', 'CUPO_EXT')
            ->selectRaw('escuelas.id, escuelas.nombre_corto, escuelas.nombre, count(*) as total')
            ->groupBy('escuelas.id', 'escuelas.nombre_corto', 'escuelas.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => [
                'escuela' => $r->nombre_corto ?? $r->nombre,
                'total'   => (int) $r->total,
            ]);

        return $this->success([
            'por_estado' => [
                'pendiente'   => (int) ($porEstado['pendiente']   ?? 0),
                'en_revision' => (int) ($porEstado['en_revision'] ?? 0),
                'aprobada'    => (int) ($porEstado['aprobada']    ?? 0),
                'rechazada'   => (int) ($porEstado['rechazada']   ?? 0),
            ],
            'total'       => (int) $porEstado->sum(),
            'por_tipo'    => [
                'cupo_ext'     => (int) ($porTipo['CUPO_EXT']     ?? 0),
                'insc_escuela' => (int) ($porTipo['INSC_ESCUELA'] ?? 0),
            ],
            'cursos_top'  => $cursosTop,
            'por_escuela' => $porEscuela,
        ], 'Estadísticas de solicitudes');
    }

    /**
     * Métricas de cupo extra por curso:
     * - Secciones programadas con inscritos
     * - Lista de solicitantes por curso
     */
    public function metricasCupo(): JsonResponse
    {
        // Todas las solicitudes CUPO_EXT con relaciones
        $solicitudes = Solicitud::whereHas('tipoSolicitud', fn($q) => $q->where('codigo', 'CUPO_EXT'))
            ->whereNotNull('programacion_id')
            ->with([
                'user.escuela',
                'programacion.curso',
                'programacion.escuelaProgramada',
            ])
            ->get();

        // Agrupar por curso_id
        $porCurso = $solicitudes->groupBy(fn($s) => $s->programacion?->curso?->id);

        $result = [];

        foreach ($porCurso as $cursoId => $sols) {
            if (!$cursoId) continue;

            $curso = $sols->first()->programacion->curso;

            // Todas las secciones programadas del curso en el periodo activo
            $secciones = ProgramacionAcademica::where('curso_id', $cursoId)
                ->whereHas('periodo', fn($q) => $q->where('activo', true))
                ->with(['docente', 'aulaRelacion', 'escuelaProgramada'])
                ->orderBy('grupo')
                ->get()
                ->map(fn($p) => [
                    'id'               => $p->id,
                    'grupo'            => $p->grupo,
                    'seccion'          => $p->seccion,
                    'n_inscritos'      => $p->n_inscritos,
                    'capacidad'        => $p->capacidad,
                    'docente'          => $p->docente?->nombre,
                    'aula'             => $p->aulaRelacion?->nombre,
                    'escuela_programada' => $p->escuelaProgramada?->nombre_corto ?? $p->escuelaProgramada?->nombre,
                    'lleno'            => $p->lleno_manual || ($p->n_inscritos !== null && $p->capacidad !== null && $p->n_inscritos >= $p->capacidad),
                ]);

            // Lista de solicitantes
            $solicitantes = $sols->map(fn($s) => [
                'id'              => $s->id,
                'programacion_id' => $s->programacion_id,
                'estado'          => $s->estado,
                'fecha'           => $s->created_at->format('d/m/Y H:i'),
                'fuera_de_plan'   => (bool) $s->fuera_de_plan,
                'codigo'          => $s->user?->codigo_universitario,
                'nombre'          => $s->user?->name,
                'escuela'         => $s->user?->escuela?->nombre_corto ?? $s->user?->escuela?->nombre,
                'seccion_solicitada' => $s->programacion?->seccion,
                'grupo_solicitado'   => $s->programacion?->grupo,
            ])->values();

            $result[] = [
                'curso_id'  => $cursoId,
                'codigo'    => $curso->codigo,
                'nombre'    => $curso->nombre,
                'total'     => $sols->count(),
                'por_estado' => [
                    'pendiente'   => $sols->where('estado', 'pendiente')->count(),
                    'en_revision' => $sols->where('estado', 'en_revision')->count(),
                    'aprobada'    => $sols->where('estado', 'aprobada')->count(),
                    'rechazada'   => $sols->where('estado', 'rechazada')->count(),
                ],
                'secciones'    => $secciones->values(),
                'solicitantes' => $solicitantes,
            ];
        }

        // Ordenar por total de solicitudes desc
        usort($result, fn($a, $b) => $b['total'] - $a['total']);

        return $this->success($result, 'Métricas de cupo extra');
    }

    /**
     * Actualizar estado de una solicitud (admin/secretaria/decano)
     */
    public function updateEstado(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'estado' => 'required|in:pendiente,en_revision,aprobada,rechazada',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $solicitud = $this->service->findById($id);

        if (!$solicitud) {
            return $this->notFound('Solicitud no encontrada');
        }

        $solicitud = $this->service->updateEstado(
            $id,
            $request->estado,
            $request->observaciones,
            $request->user()
        );

        return $this->success(
            $this->transformer->toArray($solicitud),
            'Estado actualizado exitosamente'
        );
    }

}
