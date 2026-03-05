<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CursoService;
use App\Transformers\CursoTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CursoController extends Controller
{
    public function __construct(
        protected CursoService $service,
        protected CursoTransformer $transformer
    ) {}

    public function index(Request $request): JsonResponse
    {
        $result = $this->service->getPaginated($request);

        $items = $this->transformer->collection(collect($result->items()));

        return $this->paginated($items, $result, 'Lista de cursos');
    }

    public function show(string $id): JsonResponse
    {
        $curso = $this->service->findById($id);

        if (!$curso) {
            return $this->notFound('Curso no encontrado');
        }

        return $this->success($this->transformer->toArray($curso));
    }
}
