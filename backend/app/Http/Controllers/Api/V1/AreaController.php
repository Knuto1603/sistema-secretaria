<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaPrefijo;
use App\Models\Curso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreaController extends Controller
{
    public function index(): JsonResponse
    {
        $areas = Area::with('prefijos')
            ->withCount('cursos')
            ->orderBy('nombre')
            ->get()
            ->map(fn($a) => $this->formatArea($a));

        return $this->success($areas, 'Lista de departamentos');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'     => 'required|string|max:150|unique:areas,nombre',
            'prefijos'   => 'sometimes|array',
            'prefijos.*' => 'string|max:10',
        ]);

        $prefijos = array_map('strtoupper', array_map('trim', $data['prefijos'] ?? []));

        // Verificar que ningún prefijo ya exista en otro área
        if (!empty($prefijos)) {
            $existente = AreaPrefijo::whereIn('prefijo', $prefijos)->first();
            if ($existente) {
                return $this->error(
                    "El prefijo \"{$existente->prefijo}\" ya está asignado a otro departamento.",
                    422
                );
            }
        }

        $area = Area::create(['nombre' => $data['nombre']]);

        foreach ($prefijos as $prefijo) {
            $area->prefijos()->create(['prefijo' => $prefijo]);
        }

        $area->load('prefijos');
        $area->loadCount('cursos');

        return $this->success($this->formatArea($area), 'Departamento creado', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $area = Area::find($id);
        if (!$area) return $this->notFound('Departamento no encontrado');

        $data = $request->validate([
            'nombre'     => 'required|string|max:150|unique:areas,nombre,' . $id,
            'prefijos'   => 'sometimes|array',
            'prefijos.*' => 'string|max:10',
        ]);

        $prefijos = array_map('strtoupper', array_map('trim', $data['prefijos'] ?? []));

        // Verificar que los nuevos prefijos no estén en otro área distinta
        if (!empty($prefijos)) {
            $existente = AreaPrefijo::whereIn('prefijo', $prefijos)
                ->where('area_id', '!=', $id)
                ->first();
            if ($existente) {
                return $this->error(
                    "El prefijo \"{$existente->prefijo}\" ya está asignado a otro departamento.",
                    422
                );
            }
        }

        $area->update(['nombre' => $data['nombre']]);

        // Reemplazar prefijos (delete + insert)
        if (array_key_exists('prefijos', $data)) {
            $area->prefijos()->delete();
            foreach ($prefijos as $prefijo) {
                $area->prefijos()->create(['prefijo' => $prefijo]);
            }
        }

        $area->load('prefijos');
        $area->loadCount('cursos');

        return $this->success($this->formatArea($area), 'Departamento actualizado');
    }

    public function destroy(string $id): JsonResponse
    {
        $area = Area::find($id);
        if (!$area) return $this->notFound('Departamento no encontrado');

        if ($area->cursos()->exists()) {
            return $this->error(
                'No se puede eliminar: tiene cursos asignados. Reasigna los cursos primero.',
                422
            );
        }

        $area->delete();

        return $this->success(null, 'Departamento eliminado');
    }

    public function autoAsignar(): JsonResponse
    {
        $prefijos = AreaPrefijo::all();
        $asignados = 0;

        foreach ($prefijos as $prefijo) {
            $affected = Curso::whereRaw('LEFT(codigo, ?) = ?', [
                strlen($prefijo->prefijo),
                $prefijo->prefijo,
            ])->update(['area_id' => $prefijo->area_id]);

            $asignados += $affected;
        }

        return $this->success(['asignados' => $asignados], "Se asignaron {$asignados} cursos a sus departamentos");
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function formatArea(Area $area): array
    {
        return [
            'id'           => $area->id,
            'nombre'       => $area->nombre,
            'prefijos'     => $area->prefijos->pluck('prefijo')->toArray(),
            'cursos_count' => $area->cursos_count ?? $area->cursos()->count(),
        ];
    }
}
