<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\SolicitudApertura\CreateSolicitudAperturaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudApertura\CreateSolicitudAperturaRequest;
use App\Services\SolicitudAperturaService;
use App\Transformers\SolicitudAperturaTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class SolicitudAperturaController extends Controller
{
    public function __construct(
        protected SolicitudAperturaService $service,
        protected SolicitudAperturaTransformer $transformer
    ) {}

    /**
     * Crear nueva solicitud de apertura de curso (estudiantes)
     */
    public function store(CreateSolicitudAperturaRequest $request): JsonResponse
    {
        try {
            $dto = CreateSolicitudAperturaDTO::fromRequest($request->validated());
            $solicitud = $this->service->create($dto, $request->user());

            return $this->created(
                $this->transformer->toArray($solicitud),
                'Solicitud de apertura enviada exitosamente'
            );
        } catch (Exception $e) {
            $businessErrors = [
                'Ya tienes una solicitud',
                'periodo académico activo',
                'presentación de solicitudes',
                'no tienes una escuela',
                'no corresponde al curso',
            ];
            $isBusinessError = collect($businessErrors)->contains(fn($msg) => str_contains($e->getMessage(), $msg));
            $code = $isBusinessError ? 422 : 500;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * Buscar en el catálogo completo de cursos (estudiantes) — detecta si el curso ya
     * está programado este periodo para su escuela y sugiere el tipo de solicitud.
     */
    public function buscarCurso(Request $request): JsonResponse
    {
        $result = $this->service->buscarCurso($request->user(), $request);
        return $this->paginated($result['items'], $result['paginator'], 'Cursos');
    }

    /**
     * Listar solicitudes de apertura del usuario autenticado (estudiantes)
     */
    public function misSolicitudes(Request $request): JsonResponse
    {
        $result = $this->service->getByUser($request->user(), $request);
        $items = $this->transformer->collection(collect($result->items()));

        return $this->paginated($items, $result, 'Mis solicitudes de apertura');
    }

    /**
     * Listar todas las solicitudes individuales (admin/secretaria/decano)
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->getAll($request);
        $items = $this->transformer->collection(collect($result->items()));

        return $this->paginated($items, $result, 'Lista de solicitudes de apertura');
    }

    /**
     * Listado agrupado por curso con indicadores de prioridad (vista principal admin)
     */
    public function agrupado(Request $request): JsonResponse
    {
        $items = $this->service->getAgrupado($request);
        return $this->success($items, 'Solicitudes de apertura agrupadas por curso');
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

        $this->service->updateEstado($id, 'anulada');

        return $this->success(null, 'Solicitud anulada correctamente');
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

        $solicitud = $this->service->updateEstado($id, $request->estado, $request->observaciones);

        return $this->success($this->transformer->toArray($solicitud), 'Estado actualizado exitosamente');
    }

    /**
     * Cambiar el estado de varias solicitudes a la vez (ej. aprobar todas las de un curso)
     */
    public function updateEstadoMasivo(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string|exists:solicitudes_apertura_curso,id',
            'estado' => 'required|in:pendiente,en_revision,aprobada,rechazada',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        $actualizadas = $this->service->updateEstadoMasivo($request->ids, $request->estado, $request->observaciones);

        return $this->success(
            ['actualizadas' => $actualizadas],
            "{$actualizadas} solicitud(es) actualizada(s) exitosamente"
        );
    }
}
