<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Aula;
use App\Models\Pabellon;
use App\Models\ProgramacionAcademica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PabellonController extends Controller
{
    // ─── Pabellones ────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $pabellones = Pabellon::with(['aulas' => fn($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get()
            ->map(fn($p) => $this->formatPabellon($p));

        return $this->success($pabellones, 'Lista de pabellones');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:100|unique:pabellones,nombre',
        ]);

        $pabellon = Pabellon::create($data);
        $pabellon->load('aulas');

        return $this->success($this->formatPabellon($pabellon), 'Pabellón creado', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $pabellon = Pabellon::find($id);
        if (!$pabellon) return $this->notFound('Pabellón no encontrado');

        $data = $request->validate([
            'nombre' => 'sometimes|string|max:100|unique:pabellones,nombre,' . $id,
            'activo' => 'sometimes|boolean',
        ]);

        $pabellon->update($data);
        $pabellon->load('aulas');

        return $this->success($this->formatPabellon($pabellon), 'Pabellón actualizado');
    }

    public function destroy(string $id): JsonResponse
    {
        $pabellon = Pabellon::find($id);
        if (!$pabellon) return $this->notFound('Pabellón no encontrado');

        if ($pabellon->aulas()->exists()) {
            return $this->error('No se puede eliminar: tiene aulas registradas. Elimínalas primero.', 422);
        }

        $pabellon->delete();

        return $this->success(null, 'Pabellón eliminado');
    }

    // ─── Aulas ──────────────────────────────────────────────────────────────

    public function storeAula(Request $request, string $pabellonId): JsonResponse
    {
        $pabellon = Pabellon::find($pabellonId);
        if (!$pabellon) return $this->notFound('Pabellón no encontrado');

        $data = $request->validate([
            'nombre'     => 'required|string|max:20',
            'capacidad'  => 'required|integer|min:1|max:500',
        ]);

        $data['activo'] = true;

        $aula = $pabellon->aulas()->create($data);

        return $this->success($this->formatAula($aula), 'Aula creada', 201);
    }

    public function indexHuerfanas(): JsonResponse
    {
        $huerfanas = Aula::whereNull('pabellon_id')
            ->orderBy('nombre')
            ->get()
            ->map(fn($a) => $this->formatAula($a));

        return $this->success($huerfanas, 'Aulas sin pabellón');
    }

    public function eliminarHuerfanasSinCurso(): JsonResponse
    {
        // Aulas sin pabellón Y sin ninguna programación asociada
        $aulasEnUso = ProgramacionAcademica::select('aula_id')
            ->whereNotNull('aula_id')
            ->distinct();

        $eliminadas = Aula::whereNull('pabellon_id')
            ->whereNotIn('id', $aulasEnUso)
            ->delete();

        return $this->success(
            ['eliminadas' => $eliminadas],
            "{$eliminadas} aula(s) huérfana(s) sin curso eliminadas"
        );
    }

    public function updateAula(Request $request, string $id): JsonResponse
    {
        $aula = Aula::find($id);
        if (!$aula) return $this->notFound('Aula no encontrada');

        $data = $request->validate([
            'nombre'      => 'sometimes|string|max:20',
            'capacidad'   => 'sometimes|integer|min:1|max:500',
            'activo'      => 'sometimes|boolean',
            'pabellon_id' => 'sometimes|nullable|uuid|exists:pabellones,id',
        ]);

        $aula->update($data);
        $aula->load('pabellon');

        return $this->success($this->formatAula($aula), 'Aula actualizada');
    }

    public function destroyAula(string $id): JsonResponse
    {
        $aula = Aula::find($id);
        if (!$aula) return $this->notFound('Aula no encontrada');

        if ($aula->programaciones()->exists()) {
            return $this->error('No se puede eliminar: tiene programaciones asociadas.', 422);
        }

        $aula->delete();

        return $this->success(null, 'Aula eliminada');
    }

    public function toggleAula(string $id): JsonResponse
    {
        $aula = Aula::find($id);
        if (!$aula) return $this->notFound('Aula no encontrada');

        $aula->update(['activo' => !$aula->activo]);

        return $this->success($this->formatAula($aula), $aula->activo ? 'Aula activada' : 'Aula desactivada');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function formatPabellon(Pabellon $p): array
    {
        return [
            'id'     => $p->id,
            'nombre' => $p->nombre,
            'activo' => $p->activo,
            'aulas'  => $p->aulas->map(fn($a) => $this->formatAula($a))->toArray(),
        ];
    }

    private function formatAula(Aula $a): array
    {
        return [
            'id'          => $a->id,
            'pabellon_id' => $a->pabellon_id,
            'nombre'      => $a->nombre,
            'capacidad'   => $a->capacidad,
            'activo'      => $a->activo,
        ];
    }
}
