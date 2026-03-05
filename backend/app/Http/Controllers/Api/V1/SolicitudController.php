<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Solicitud\CreateSolicitudDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitud\CreateSolicitudRequest;
use App\Services\SolicitudService;
use App\Transformers\SolicitudTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
