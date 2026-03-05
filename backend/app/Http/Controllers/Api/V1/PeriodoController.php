<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Periodo\CreatePeriodoDTO;
use App\DTOs\Periodo\UpdatePeriodoDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Periodo\CreatePeriodoRequest;
use App\Http\Requests\Periodo\UpdatePeriodoRequest;
use App\Services\PeriodoService;
use Illuminate\Http\JsonResponse;

class PeriodoController extends Controller
{
    public function __construct(
        protected PeriodoService $service
    ) {}

    public function index(): JsonResponse
    {
        $periodos = $this->service->getAll();
        return $this->success($periodos, 'Lista de periodos');
    }

    public function show(string $id): JsonResponse
    {
        $periodo = $this->service->getById($id);

        if (!$periodo) {
            return $this->notFound('Periodo no encontrado');
        }

        return $this->success($periodo);
    }

    public function store(CreatePeriodoRequest $request): JsonResponse
    {
        $dto = CreatePeriodoDTO::fromRequest($request->validated());
        $periodo = $this->service->create($dto);

        return $this->created($periodo, 'Periodo creado exitosamente');
    }

    public function update(UpdatePeriodoRequest $request, string $id): JsonResponse
    {
        $dto = UpdatePeriodoDTO::fromRequest($request->validated());
        $periodo = $this->service->update($id, $dto);

        if (!$periodo) {
            return $this->notFound('Periodo no encontrado');
        }

        return $this->success($periodo, 'Periodo actualizado exitosamente');
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->service->delete($id);

        if (!$deleted) {
            return $this->notFound('Periodo no encontrado');
        }

        return $this->success(null, 'Periodo eliminado exitosamente');
    }

    public function active(): JsonResponse
    {
        $periodo = $this->service->getActive();

        if (!$periodo) {
            return $this->notFound('No hay periodo activo');
        }

        return $this->success($periodo, 'Periodo activo');
    }

    public function setActive(string $id): JsonResponse
    {
        $periodo = $this->service->setActive($id);

        if (!$periodo) {
            return $this->notFound('Periodo no encontrado');
        }

        return $this->success($periodo, 'Periodo activado exitosamente');
    }

    public function deactivate(string $id): JsonResponse
    {
        $periodo = $this->service->deactivate($id);

        if (!$periodo) {
            return $this->notFound('Periodo no encontrado');
        }

        return $this->success($periodo, 'Periodo desactivado exitosamente');
    }
}
