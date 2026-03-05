<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\PlanEstudiosTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PlanEstudiosImport;
use App\Models\Escuela;
use App\Models\PlanEstudios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanEstudiosController extends Controller
{
    /**
     * Lista el plan de estudios de una escuela
     * GET /plan-estudios?escuela_codigo=0
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo' => ['required', 'in:0,1,2,3'],
        ]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);

        if (! $escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $plan = PlanEstudios::with('curso.area')
            ->where('escuela_id', $escuela->id)
            ->orderBy('ciclo')
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'ciclo'        => $p->ciclo,
                'creditos'     => $p->creditos,
                'tipo'         => $p->tipo,  // O = Obligatorio, E = Electivo
                'curso_id'     => $p->curso_id,
                'codigo_curso' => $p->curso->codigo,
                'nombre_curso' => $p->curso->nombre,
                'area'         => $p->curso->area?->nombre,
            ]);

        return $this->success([
            'escuela' => [
                'codigo'      => $escuela->codigo,
                'nombre'      => $escuela->nombre,
                'nombre_corto'=> $escuela->nombre_corto,
            ],
            'cursos' => $plan,
            'total'  => $plan->count(),
        ], 'Plan de estudios');
    }

    /**
     * Importa el plan de estudios de una escuela desde Excel
     * POST /plan-estudios/import
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo' => ['required', 'in:0,1,2,3'],
            'archivo'        => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new PlanEstudiosImport($request->escuela_codigo);
        Excel::import($import, $request->file('archivo'));

        $resumen = $import->getResumen();

        return $this->success([
            'resumen'    => $resumen,
            'resultados' => $import->getResultados(),
        ], "Plan importado: {$resumen['importados']} cursos, {$resumen['errores']} errores.");
    }

    /**
     * Elimina todos los cursos del plan de una escuela (para reimportar)
     * DELETE /plan-estudios?escuela_codigo=0
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo' => ['required', 'in:0,1,2,3'],
        ]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);

        if (! $escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $eliminados = PlanEstudios::where('escuela_id', $escuela->id)->delete();

        return $this->success(['eliminados' => $eliminados], 'Plan de estudios eliminado');
    }

    /**
     * Descarga la plantilla Excel para el plan de estudios
     * GET /plan-estudios/template
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new PlanEstudiosTemplateExport(), 'plantilla_plan_estudios.xlsx');
    }
}
