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
            'nombre'          => 'required|string|max:150|unique:areas,nombre',
            'prefijos'        => 'sometimes|array',
            'prefijos.*'      => 'string|max:10',
            'titulo_director' => 'nullable|string|max:20',
            'director_nombre' => 'nullable|string|max:150',
            'director_cargo'  => 'nullable|string|max:250',
            'nombre_tabla'    => 'nullable|string|max:100',
        ]);

        $prefijos = array_map('strtoupper', array_map('trim', $data['prefijos'] ?? []));

        if (!empty($prefijos)) {
            $existente = AreaPrefijo::whereIn('prefijo', $prefijos)->first();
            if ($existente) {
                return $this->error("El prefijo \"{$existente->prefijo}\" ya está asignado a otro departamento.", 422);
            }
        }

        $area = Area::create([
            'nombre'          => $data['nombre'],
            'titulo_director' => $data['titulo_director'] ?? null,
            'director_nombre' => $data['director_nombre'] ?? null,
            'director_cargo'  => $data['director_cargo'] ?? null,
            'nombre_tabla'    => $data['nombre_tabla'] ?? null,
        ]);

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
            'nombre'          => 'required|string|max:150|unique:areas,nombre,' . $id,
            'prefijos'        => 'sometimes|array',
            'prefijos.*'      => 'string|max:10',
            'titulo_director' => 'nullable|string|max:20',
            'director_nombre' => 'nullable|string|max:150',
            'director_cargo'  => 'nullable|string|max:250',
            'nombre_tabla'    => 'nullable|string|max:100',
        ]);

        $prefijos = array_map('strtoupper', array_map('trim', $data['prefijos'] ?? []));

        if (!empty($prefijos)) {
            $existente = AreaPrefijo::whereIn('prefijo', $prefijos)
                ->where('area_id', '!=', $id)
                ->first();
            if ($existente) {
                return $this->error("El prefijo \"{$existente->prefijo}\" ya está asignado a otro departamento.", 422);
            }
        }

        $area->update([
            'nombre'          => $data['nombre'],
            'titulo_director' => $data['titulo_director'] ?? $area->titulo_director,
            'director_nombre' => $data['director_nombre'] ?? $area->director_nombre,
            'director_cargo'  => $data['director_cargo'] ?? $area->director_cargo,
            'nombre_tabla'    => $data['nombre_tabla'] ?? $area->nombre_tabla,
        ]);

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
            return $this->error('No se puede eliminar: tiene cursos asignados. Reasigna los cursos primero.', 422);
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

    // ─── Asignación manual curso → área ──────────────────────────────────────

    public function cursos(Request $request): JsonResponse
    {
        $query = Curso::with('area')->orderBy('codigo');

        if ($request->boolean('sin_area')) {
            $query->whereNull('area_id');
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(fn($q) => $q->where('nombre', 'like', $search)->orWhere('codigo', 'like', $search));
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        $cursos = $query->get()->map(fn($c) => $this->formatCurso($c));

        return $this->success($cursos, 'Lista de cursos');
    }

    public function asignarArea(Request $request, string $cursoId): JsonResponse
    {
        $curso = Curso::find($cursoId);
        if (!$curso) return $this->notFound('Curso no encontrado');

        $data = $request->validate([
            'area_id' => 'nullable|uuid|exists:areas,id',
        ]);

        $curso->update(['area_id' => $data['area_id']]);
        $curso->load('area');

        return $this->success($this->formatCurso($curso), 'Área asignada correctamente');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatArea(Area $area): array
    {
        return [
            'id'              => $area->id,
            'nombre'          => $area->nombre,
            'titulo_director' => $area->titulo_director,
            'director_nombre' => $area->director_nombre,
            'director_cargo'  => $area->director_cargo,
            'nombre_tabla'    => $area->nombre_tabla,
            'prefijos'        => $area->prefijos->pluck('prefijo')->toArray(),
            'cursos_count'    => $area->cursos_count ?? $area->cursos()->count(),
        ];
    }

    private function formatCurso(Curso $curso): array
    {
        return [
            'id'     => $curso->id,
            'codigo' => $curso->codigo,
            'nombre' => $curso->nombre,
            'area'   => $curso->area ? ['id' => $curso->area->id, 'nombre' => $curso->area->nombre] : null,
        ];
    }
}
