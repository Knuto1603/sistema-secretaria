<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Modificacion\ModificacionFilterDTO;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ModificacionRepositoryInterface;
use App\Transformers\ModificacionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModificacionController extends Controller
{
    public function __construct(
        protected ModificacionRepositoryInterface $repository,
        protected ModificacionTransformer $transformer
    ) {}

    /**
     * GET /modificaciones
     * Historial paginado con filtros opcionales.
     */
    public function index(Request $request): JsonResponse
    {
        $filters  = ModificacionFilterDTO::fromRequest($request->all());
        $paginator = $this->repository->paginate($filters);

        $items = $this->transformer->collection($paginator->items());

        return $this->paginated($items, $paginator, 'Historial de modificaciones');
    }

    /**
     * GET /modificaciones/{id}
     * Detalle de una modificación.
     */
    public function show(string $id): JsonResponse
    {
        $modificacion = $this->repository->findById($id);

        if (!$modificacion) {
            return $this->notFound('Modificación no encontrada.');
        }

        return $this->success($this->transformer->toArray($modificacion), 'Detalle de modificación');
    }
}
