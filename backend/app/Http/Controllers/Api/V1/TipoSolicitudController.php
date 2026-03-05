<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\TipoSolicitud\CreateTipoSolicitudDTO;
use App\DTOs\TipoSolicitud\UpdateTipoSolicitudDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\TipoSolicitud\CreateTipoSolicitudRequest;
use App\Http\Requests\TipoSolicitud\UpdateTipoSolicitudRequest;
use App\Services\TipoSolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TipoSolicitudController extends Controller
{
    public function __construct(
        protected TipoSolicitudService $service
    ) {}

    public function index(): JsonResponse
    {
        $tipos = $this->service->getAll();
        return $this->success($tipos, 'Lista de tipos de solicitud');
    }

    public function show(string $id): JsonResponse
    {
        $tipo = $this->service->getById($id);

        if (!$tipo) {
            return $this->notFound('Tipo de solicitud no encontrado');
        }

        return $this->success($tipo);
    }

    public function store(CreateTipoSolicitudRequest $request): JsonResponse
    {
        $dto = CreateTipoSolicitudDTO::fromRequest($request->validated());
        $tipo = $this->service->create($dto);

        return $this->created($tipo, 'Tipo de solicitud creado exitosamente');
    }

    public function update(UpdateTipoSolicitudRequest $request, string $id): JsonResponse
    {
        $dto = UpdateTipoSolicitudDTO::fromRequest($request->validated());
        $tipo = $this->service->update($id, $dto);

        if (!$tipo) {
            return $this->notFound('Tipo de solicitud no encontrado');
        }

        return $this->success($tipo, 'Tipo de solicitud actualizado');
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->notFound('Tipo de solicitud no encontrado');
        }

        return $this->success(null, 'Tipo de solicitud eliminado');
    }

    public function toggle(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'activo' => 'required|boolean'
        ]);

        $tipo = $this->service->toggleActivo($id, $request->activo);

        if (!$tipo) {
            return $this->notFound('Tipo de solicitud no encontrado');
        }

        return $this->success($tipo, $request->activo ? 'Tipo activado' : 'Tipo desactivado');
    }
}
