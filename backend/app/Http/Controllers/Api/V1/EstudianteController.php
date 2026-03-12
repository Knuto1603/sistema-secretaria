<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EstudianteTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\ImportAlumnosHtmlRequest;
use App\Http\Requests\Usuario\UpdateEstudianteRequest;
use App\Imports\AlumnosHtmlImport;
use App\Imports\EstudianteImport;
use App\Services\EstudianteService;
use App\Services\ImportHistorialesZipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EstudianteController extends Controller
{
    public function __construct(
        protected EstudianteService $service
    ) {}

    /**
     * Lista estudiantes con paginación y filtros
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'escuela_codigo' => $request->get('escuela_codigo'),
            'cuenta_activada' => $request->has('cuenta_activada')
                ? filter_var($request->get('cuenta_activada'), FILTER_VALIDATE_BOOLEAN)
                : null,
            'activo' => $request->has('activo')
                ? filter_var($request->get('activo'), FILTER_VALIDATE_BOOLEAN)
                : null,
        ];

        $perPage = (int) $request->get('per_page', 15);

        $result = $this->service->paginate($filters, $perPage);

        return $this->success($result, 'Lista de estudiantes');
    }

    /**
     * Obtiene un estudiante por ID
     */
    public function show(string $id): JsonResponse
    {
        $estudiante = $this->service->getById($id);

        if (!$estudiante) {
            return $this->notFound('Estudiante no encontrado');
        }

        return $this->success($estudiante);
    }

    /**
     * Crea un nuevo estudiante
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'codigo_universitario' => ['required', 'digits:10', 'unique:users,codigo_universitario'],
        ]);

        $result = $this->service->create($data);

        return $this->success($result, 'Estudiante creado exitosamente', 201);
    }

    /**
     * Actualiza datos de un estudiante
     */
    public function update(UpdateEstudianteRequest $request, string $id): JsonResponse
    {
        $result = $this->service->update($id, $request->validated());

        if (!$result) {
            return $this->notFound('Estudiante no encontrado');
        }

        return $this->success($result, 'Estudiante actualizado exitosamente');
    }

    /**
     * Activa o desactiva un estudiante
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'activo' => 'required|boolean'
        ]);

        $result = $this->service->toggleActivo($id, $request->activo);

        if (!$result) {
            return $this->notFound('Estudiante no encontrado');
        }

        $message = $request->activo ? 'Estudiante activado' : 'Estudiante desactivado';
        return $this->success($result, $message);
    }

    /**
     * Reenvía OTP a un estudiante
     */
    public function reenviarOtp(string $id): JsonResponse
    {
        $result = $this->service->reenviarOtp($id);

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result['data'] ?? null, $result['message']);
    }

    /**
     * Importa estudiantes desde un archivo Excel
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new EstudianteImport();
        Excel::import($import, $request->file('archivo'));

        $resumen = $import->getResumen();
        $resultados = $import->getResultados();

        return $this->success([
            'resumen'     => $resumen,
            'resultados'  => $resultados,
        ], "Importación completada: {$resumen['importados']} importados, {$resumen['omitidos']} omitidos, {$resumen['errores']} errores.");
    }

    /**
     * Descarga la plantilla Excel para importar estudiantes
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new EstudianteTemplateExport(), 'plantilla_estudiantes.xlsx');
    }

    /**
     * Importa estudiantes y sus historiales desde un ZIP con archivos HTM del SIGA
     */
    public function importHistorialesZip(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:zip', 'max:102400'], // máx 100MB
        ]);

        try {
            $service = new ImportHistorialesZipService();
            $resumen = $service->import($request->file('archivo'));

            $total = $resumen['estudiantes_creados'] + $resumen['estudiantes_actualizados'];
            $msg   = "Procesados {$total} estudiantes: {$resumen['estudiantes_creados']} nuevos, "
                   . "{$resumen['estudiantes_actualizados']} actualizados. "
                   . "Historial: {$resumen['historial_insertado']} insertados, "
                   . "{$resumen['historial_actualizado']} actualizados.";

            return $this->success($resumen, $msg);
        } catch (\Exception $e) {
            return $this->error('Error al procesar el ZIP: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Importa estudiantes desde padrón HTML del SIGA (ALUMNOS 20260.htm)
     */
    public function importHtml(ImportAlumnosHtmlRequest $request): JsonResponse
    {
        try {
            $importer = new AlumnosHtmlImport();
            $importer->import($request->file('file')->getPathname());

            return $this->success(
                $importer->getResumen(),
                'Estudiantes importados desde padrón SIGA'
            );
        } catch (\Exception $e) {
            return $this->error('Error al procesar el archivo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /usuarios/estudiantes/{id}/historial
     * Historial académico de un estudiante (vista admin)
     */
    public function historial(string $id): JsonResponse
    {
        $estudiante = \App\Models\User::where('id', $id)
            ->where('tipo_usuario', 'estudiante')
            ->first();

        if (!$estudiante) {
            return $this->notFound('Estudiante no encontrado');
        }

        $historial = \App\Models\HistorialAcademico::where('user_id', $id)
            ->with('curso')
            ->orderByRaw("CASE WHEN semestre IS NULL THEN 1 ELSE 0 END")
            ->orderBy('semestre', 'desc')
            ->get();

        $porSemestre = $historial->filter(fn($h) => !is_null($h->semestre))
            ->groupBy('semestre')
            ->map(fn($items, $semestre) => [
                'semestre' => $semestre,
                'cursos'   => $items->map(fn($h) => [
                    'id'       => $h->id,
                    'curso'    => $h->curso ? ['id' => $h->curso->id, 'nombre' => $h->curso->nombre, 'codigo' => $h->curso->codigo] : null,
                    'nota'     => $h->nota,
                    'creditos' => $h->creditos,
                    'tipo'     => $h->tipo,
                    'fuente'   => $h->fuente,
                ])->values(),
            ])->values();

        $sinSemestre = $historial->filter(fn($h) => is_null($h->semestre))
            ->map(fn($h) => [
                'id'       => $h->id,
                'curso'    => $h->curso ? ['id' => $h->curso->id, 'nombre' => $h->curso->nombre, 'codigo' => $h->curso->codigo] : null,
                'nota'     => $h->nota,
                'creditos' => $h->creditos,
                'tipo'     => $h->tipo,
                'fuente'   => $h->fuente,
            ])->values();

        return $this->success([
            'por_semestre' => $porSemestre,
            'sin_semestre' => $sinSemestre,
            'total'        => $historial->count(),
        ], 'Historial académico del estudiante');
    }

    /**
     * GET /usuarios/estudiantes/{id}/inscripciones
     * Inscripciones de un estudiante (vista admin)
     */
    public function inscripciones(string $id): JsonResponse
    {
        $estudiante = \App\Models\User::where('id', $id)
            ->where('tipo_usuario', 'estudiante')
            ->first();

        if (!$estudiante) {
            return $this->notFound('Estudiante no encontrado');
        }

        $inscripciones = \App\Models\Inscripcion::where('user_id', $id)
            ->with(['programacion.curso', 'programacion.periodo', 'periodo'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($i) => [
                'id'      => $i->id,
                'fuente'  => $i->fuente,
                'periodo' => $i->periodo
                    ? ['id' => $i->periodo->id, 'nombre' => $i->periodo->nombre]
                    : ($i->programacion?->periodo
                        ? ['id' => $i->programacion->periodo->id, 'nombre' => $i->programacion->periodo->nombre]
                        : null),
                'programacion' => $i->programacion ? [
                    'id'      => $i->programacion->id,
                    'clave'   => $i->programacion->clave,
                    'seccion' => $i->programacion->seccion,
                    'grupo'   => $i->programacion->grupo,
                    'curso'   => $i->programacion->curso
                        ? ['nombre' => $i->programacion->curso->nombre, 'codigo' => $i->programacion->curso->codigo]
                        : null,
                ] : null,
                'created_at' => $i->created_at->toISOString(),
            ]);

        return $this->success($inscripciones, 'Inscripciones del estudiante');
    }
}
