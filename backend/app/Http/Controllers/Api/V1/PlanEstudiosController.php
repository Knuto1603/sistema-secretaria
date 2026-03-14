<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\PlanEstudiosTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\PlanEstudiosImport;
use App\Imports\PlanEstudiosPdfImport;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\Plan;
use App\Models\PlanEstudios;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanEstudiosController extends Controller
{
    // =========================================================================
    // PLANES (versiones)
    // =========================================================================

    /**
     * Lista todos los planes de una escuela
     * GET /plan-estudios/planes?escuela_codigo=X
     */
    public function planes(Request $request): JsonResponse
    {
        $request->validate(['escuela_codigo' => ['required', 'in:0,1,2,3']]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);
        if (!$escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $planes = Plan::where('escuela_id', $escuela->id)
            ->withCount('cursos')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => [
                'id'                            => $p->id,
                'nombre'                        => $p->nombre,
                'activo'                        => $p->activo,
                'total_creditos_obligatorios'   => $p->total_creditos_obligatorios,
                'creditos_electivos_requeridos' => $p->creditos_electivos_requeridos,
                'total_cursos'                  => $p->cursos_count,
            ]);

        return $this->success([
            'escuela' => ['codigo' => $escuela->codigo, 'nombre' => $escuela->nombre],
            'planes'  => $planes,
        ], 'Planes de estudio');
    }

    /**
     * Crea un nuevo plan (versión) para una escuela
     * POST /plan-estudios/planes
     */
    public function crearPlan(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo'                => ['required', 'in:0,1,2,3'],
            'nombre'                        => ['required', 'string', 'max:100'],
            'total_creditos_obligatorios'   => ['nullable', 'integer', 'min:0'],
            'creditos_electivos_requeridos' => ['nullable', 'integer', 'min:0'],
        ]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);
        if (!$escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $existe = Plan::where('escuela_id', $escuela->id)
            ->where('nombre', $request->nombre)
            ->exists();

        if ($existe) {
            return $this->error("Ya existe un plan llamado '{$request->nombre}' para esta escuela.", 422);
        }

        $plan = Plan::create([
            'nombre'                        => $request->nombre,
            'escuela_id'                    => $escuela->id,
            'activo'                        => false,
            'total_creditos_obligatorios'   => $request->integer('total_creditos_obligatorios', 0),
            'creditos_electivos_requeridos' => $request->integer('creditos_electivos_requeridos', 0),
        ]);

        return $this->success([
            'id'                            => $plan->id,
            'nombre'                        => $plan->nombre,
            'activo'                        => $plan->activo,
            'total_creditos_obligatorios'   => $plan->total_creditos_obligatorios,
            'creditos_electivos_requeridos' => $plan->creditos_electivos_requeridos,
            'total_cursos'                  => 0,
        ], 'Plan creado correctamente');
    }

    /**
     * Activa un plan (desactiva los demás de la misma escuela)
     * PATCH /plan-estudios/planes/{id}/activar
     */
    public function activarPlan(string $id): JsonResponse
    {
        $plan = Plan::find($id);
        if (!$plan) {
            return $this->notFound('Plan no encontrado');
        }

        DB::transaction(function () use ($plan) {
            Plan::where('escuela_id', $plan->escuela_id)->update(['activo' => false]);
            $plan->update(['activo' => true]);
        });

        return $this->success(['id' => $plan->id, 'activo' => true], 'Plan activado correctamente');
    }

    /**
     * Elimina un plan inactivo sin cursos
     * DELETE /plan-estudios/planes/{id}
     */
    public function eliminarPlan(string $id): JsonResponse
    {
        $plan = Plan::withCount('cursos')->find($id);
        if (!$plan) {
            return $this->notFound('Plan no encontrado');
        }

        if ($plan->activo) {
            return $this->error('No se puede eliminar el plan activo.', 422);
        }

        if ($plan->cursos_count > 0) {
            return $this->error("El plan tiene {$plan->cursos_count} cursos. Elimina los cursos primero.", 422);
        }

        $plan->delete();

        return $this->success(null, 'Plan eliminado correctamente');
    }

    // =========================================================================
    // CURSOS DEL PLAN (usando plan activo)
    // =========================================================================

    /**
     * Lista el plan de estudios activo de una escuela
     * GET /plan-estudios?escuela_codigo=0
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo' => ['required', 'in:0,1,2,3'],
        ]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);
        if (!$escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $plan = Plan::where('escuela_id', $escuela->id)->where('activo', true)->first();

        $query = PlanEstudios::with('curso.area');
        if ($plan) {
            $query->where('plan_id', $plan->id);
        } else {
            $query->where('escuela_id', $escuela->id);
        }

        $cursos = $query->orderBy('ciclo')->get()->map(fn($p) => [
            'id'              => $p->id,
            'ciclo'           => $p->ciclo,
            'creditos'        => $p->creditos,
            'tipo'            => $p->tipo,
            'horas_teoricas'  => $p->horas_teoricas,
            'horas_practicas' => $p->horas_practicas,
            'curso_id'        => $p->curso_id,
            'codigo_curso'    => $p->curso->codigo,
            'nombre_curso'    => $p->curso->nombre,
            'area'            => $p->curso->area?->nombre,
        ]);

        return $this->success([
            'escuela' => [
                'codigo'       => $escuela->codigo,
                'nombre'       => $escuela->nombre,
                'nombre_corto' => $escuela->nombre_corto,
            ],
            'plan'   => $plan ? [
                'id'                            => $plan->id,
                'nombre'                        => $plan->nombre,
                'activo'                        => $plan->activo,
                'total_creditos_obligatorios'   => $plan->total_creditos_obligatorios,
                'creditos_electivos_requeridos' => $plan->creditos_electivos_requeridos,
            ] : null,
            'cursos' => $cursos,
            'total'  => $cursos->count(),
        ], 'Plan de estudios');
    }

    /**
     * Devuelve el plan activo de la escuela del estudiante autenticado
     * GET /plan-estudios/mi-plan
     */
    public function miPlan(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isEstudiante() || !$user->escuela_id) {
            return $this->error('Solo disponible para estudiantes con escuela asignada.', 403);
        }

        $user->loadMissing('escuela');
        $escuela = $user->escuela;
        if (!$escuela) {
            return $this->notFound('Escuela no encontrada.');
        }

        $plan = Plan::where('escuela_id', $user->escuela_id)->where('activo', true)->first();

        $query = PlanEstudios::with('curso');
        if ($plan) {
            $query->where('plan_id', $plan->id);
        } else {
            $query->where('escuela_id', $user->escuela_id);
        }

        $cursos = $query->orderBy('ciclo')->get()->map(fn($p) => [
            'ciclo'    => (int) $p->ciclo,
            'curso_id' => $p->curso_id,
            'codigo'   => $p->curso->codigo,
            'nombre'   => $p->curso->nombre,
            'tipo'     => $p->tipo,
        ]);

        $ciclos = $cursos->groupBy('ciclo')
            ->map(fn($items, $ciclo) => [
                'ciclo'  => (int) $ciclo,
                'cursos' => $items->values(),
            ])
            ->values();

        return $this->success([
            'escuela'      => ['nombre' => $escuela->nombre, 'nombre_corto' => $escuela->nombre_corto],
            'ciclos'       => $ciclos,
            'total_cursos' => $cursos->count(),
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
            'plan_id'        => ['nullable', 'uuid', 'exists:planes_estudios,id'],
        ]);

        $import = new PlanEstudiosImport($request->escuela_codigo, $request->plan_id);
        Excel::import($import, $request->file('archivo'));

        $resumen = $import->getResumen();

        return $this->success([
            'resumen'    => $resumen,
            'resultados' => $import->getResultados(),
        ], "Plan importado: {$resumen['importados']} cursos, {$resumen['errores']} errores.");
    }

    /**
     * Importa el plan de estudios desde PDF del SIGA
     * POST /plan-estudios/import-pdf
     */
    public function importPdf(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo' => ['required', 'in:0,1,2,3'],
            'archivo'        => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'plan_id'        => ['nullable', 'uuid', 'exists:planes_estudios,id'],
        ]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);
        if (!$escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $filePath = $request->file('archivo')->getPathname();
        $parser   = new PlanEstudiosPdfImport();
        $data     = $parser->parse($filePath);

        if (empty($data['cursos'])) {
            return $this->error('No se pudieron extraer cursos del PDF. Verifica el formato del archivo.', 422);
        }

        // Determinar o crear el plan
        if ($request->plan_id) {
            $plan = Plan::find($request->plan_id);
            $plan->update([
                'total_creditos_obligatorios'   => $data['total_creditos_obligatorios'] ?: $plan->total_creditos_obligatorios,
                'creditos_electivos_requeridos' => $data['creditos_electivos_requeridos'] ?: $plan->creditos_electivos_requeridos,
            ]);
        } else {
            $plan = Plan::firstOrCreate(
                ['escuela_id' => $escuela->id, 'nombre' => $data['plan_nombre']],
                [
                    'activo'                        => false,
                    'total_creditos_obligatorios'   => $data['total_creditos_obligatorios'],
                    'creditos_electivos_requeridos' => $data['creditos_electivos_requeridos'],
                ]
            );
        }

        $importados = 0;
        $errores    = [];
        $codigoMap  = [];

        DB::transaction(function () use ($data, $escuela, $plan, &$importados, &$errores, &$codigoMap) {
            foreach ($data['cursos'] as $cursoDato) {
                try {
                    $curso = Curso::firstOrCreate(
                        ['codigo' => $cursoDato['codigo']],
                        ['nombre' => $cursoDato['nombre']]
                    );

                    if ($curso->nombre !== $cursoDato['nombre'] && strlen($cursoDato['nombre']) > strlen($curso->nombre)) {
                        $curso->update(['nombre' => $cursoDato['nombre']]);
                    }

                    $codigoMap[$cursoDato['codigo']] = $curso->id;

                    PlanEstudios::updateOrCreate(
                        ['plan_id' => $plan->id, 'curso_id' => $curso->id],
                        [
                            'escuela_id'      => $escuela->id,
                            'ciclo'           => $cursoDato['ciclo'],
                            'creditos'        => $cursoDato['creditos'],
                            'tipo'            => $cursoDato['tipo'],
                            'horas_teoricas'  => $cursoDato['horas_teoricas'],
                            'horas_practicas' => $cursoDato['horas_practicas'],
                        ]
                    );

                    $importados++;
                } catch (\Throwable $e) {
                    $errores[] = ['codigo' => $cursoDato['codigo'], 'error' => $e->getMessage()];
                }
            }

            // Vincular requisitos
            foreach ($data['cursos'] as $cursoDato) {
                if (empty($cursoDato['requisitos'])) {
                    continue;
                }
                $cursoId = $codigoMap[$cursoDato['codigo']] ?? null;
                if (!$cursoId) {
                    continue;
                }
                $curso = Curso::find($cursoId);
                foreach ($cursoDato['requisitos'] as $reqCodigo) {
                    $reqId = $codigoMap[$reqCodigo] ?? Curso::where('codigo', $reqCodigo)->value('id');
                    if ($reqId && !$curso->requisitos()->where('requisito_curso_id', $reqId)->exists()) {
                        $curso->requisitos()->attach($reqId);
                    }
                }
            }
        });

        return $this->success([
            'plan' => [
                'id'                            => $plan->id,
                'nombre'                        => $plan->nombre,
                'total_creditos_obligatorios'   => $plan->fresh()->total_creditos_obligatorios,
                'creditos_electivos_requeridos' => $plan->fresh()->creditos_electivos_requeridos,
            ],
            'cursos_importados'     => $importados,
            'requisitos_vinculados' => true,
            'errores'               => $errores,
        ], "PDF importado: {$importados} cursos procesados.");
    }

    /**
     * Elimina todos los cursos del plan de una escuela (para reimportar)
     * DELETE /plan-estudios?escuela_codigo=0
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'escuela_codigo' => ['required', 'in:0,1,2,3'],
            'plan_id'        => ['nullable', 'uuid', 'exists:planes_estudios,id'],
        ]);

        $escuela = Escuela::findByCodigo($request->escuela_codigo);
        if (!$escuela) {
            return $this->notFound('Escuela no encontrada');
        }

        $query = PlanEstudios::where('escuela_id', $escuela->id);
        if ($request->plan_id) {
            $query->where('plan_id', $request->plan_id);
        }

        $eliminados = $query->delete();

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
