<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Services\CursoService;
use App\Transformers\CursoTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function updateNombre(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        $curso = $this->service->updateNombre($id, $request->nombre);

        if (!$curso) {
            return $this->notFound('Curso no encontrado');
        }

        return $this->success($this->transformer->toArray($curso), 'Nombre del curso actualizado');
    }

    /**
     * Lista las equivalencias de un curso
     * GET /cursos/{id}/equivalencias
     */
    public function equivalencias(string $id): JsonResponse
    {
        $curso = Curso::with('equivalencias')->find($id);

        if (!$curso) {
            return $this->notFound('Curso no encontrado');
        }

        $equivalencias = $curso->equivalencias->map(fn($eq) => [
            'id'     => $eq->id,
            'codigo' => $eq->codigo,
            'nombre' => $eq->nombre,
        ]);

        return $this->success([
            'curso'        => ['id' => $curso->id, 'codigo' => $curso->codigo, 'nombre' => $curso->nombre],
            'equivalencias' => $equivalencias,
        ], 'Equivalencias del curso');
    }

    /**
     * Agrega una equivalencia bidireccional entre dos cursos
     * POST /cursos/{id}/equivalencias
     */
    public function agregarEquivalencia(string $id, Request $request): JsonResponse
    {
        $request->validate([
            'equivalente_id' => ['required', 'uuid', 'exists:cursos,id'],
        ]);

        if ($id === $request->equivalente_id) {
            return $this->error('Un curso no puede ser equivalente de sí mismo.', 422);
        }

        $curso      = Curso::find($id);
        $equivalente = Curso::find($request->equivalente_id);

        if (!$curso || !$equivalente) {
            return $this->notFound('Curso no encontrado');
        }

        DB::transaction(function () use ($curso, $equivalente) {
            // Dirección A → B
            if (!$curso->equivalencias()->where('equivalente_id', $equivalente->id)->exists()) {
                $curso->equivalencias()->attach($equivalente->id);
            }
            // Dirección B → A
            if (!$equivalente->equivalencias()->where('equivalente_id', $curso->id)->exists()) {
                $equivalente->equivalencias()->attach($curso->id);
            }
        });

        return $this->success([
            'curso'        => ['id' => $curso->id, 'codigo' => $curso->codigo],
            'equivalente'  => ['id' => $equivalente->id, 'codigo' => $equivalente->codigo],
        ], 'Equivalencia registrada en ambas direcciones');
    }

    /**
     * Elimina una equivalencia bidireccional
     * DELETE /cursos/{cursoId}/equivalencias/{equivalenteId}
     */
    public function eliminarEquivalencia(string $cursoId, string $equivalenteId): JsonResponse
    {
        $curso      = Curso::find($cursoId);
        $equivalente = Curso::find($equivalenteId);

        if (!$curso || !$equivalente) {
            return $this->notFound('Curso no encontrado');
        }

        DB::transaction(function () use ($curso, $equivalente) {
            $curso->equivalencias()->detach($equivalente->id);
            $equivalente->equivalencias()->detach($curso->id);
        });

        return $this->success(null, 'Equivalencia eliminada en ambas direcciones');
    }
}
