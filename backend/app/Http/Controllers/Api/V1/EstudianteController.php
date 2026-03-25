<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EstudianteTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Estudiante\ImportAlumnosHtmlRequest;
use App\Http\Requests\Usuario\UpdateEstudianteRequest;
use App\Imports\AlumnosHtmlImport;
use App\Imports\EstudianteImport;
use App\Jobs\ProcessHistorialesZipJob;
use App\Models\ImportJob;
use App\Models\User;
use App\Services\EstudianteService;
use App\Services\ProgresoAcademicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EstudianteController extends Controller
{
    public function __construct(
        protected EstudianteService $service,
        protected ProgresoAcademicoService $progresoService
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
     * Obtiene un estudiante por ID, incluyendo su progreso académico
     */
    public function show(string $id): JsonResponse
    {
        $estudiante = $this->service->getById($id);

        if (!$estudiante) {
            return $this->notFound('Estudiante no encontrado');
        }

        // Calcular progreso académico si el alumno tiene escuela
        $progreso      = null;
        $egresante     = false;
        $progresoError = null;
        $user          = User::find($id);

        if ($user && $user->escuela_id) {
            try {
                $progreso  = $this->progresoService->calcularProgreso($user);
                $egresante = $user->egresante ?? false;
            } catch (\Throwable $e) {
                Log::error('calcularProgreso falló para estudiante ' . $id . ': ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                $progresoError = 'No se pudo calcular el progreso académico.';
            }
        }

        return $this->success(array_merge($estudiante, [
            'egresante'      => $egresante,
            'progreso'       => $progreso,
            'progreso_error' => $progresoError,
        ]));
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
     * Resetea la activación de la cuenta de un estudiante
     */
    public function resetActivacion(string $id): JsonResponse
    {
        $result = $this->service->resetActivacion($id);

        if (!$result['success']) {
            return $this->error($result['message'], 400);
        }

        return $this->success($result['data'] ?? null, $result['message']);
    }

    /**
     * Inhabilita la cuenta del estudiante y resetea la activación (OTP + contraseña)
     */
    public function inhabilitarYResetear(string $id): JsonResponse
    {
        $result = $this->service->inhabilitarYResetear($id);

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
     * Importa estudiantes y sus historiales desde un ZIP con archivos HTM del SIGA.
     * Despacha un job en background y retorna un job_id para consultar el estado.
     */
    public function importHistorialesZip(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:zip', 'max:102400'], // máx 100MB
        ]);

        // Crear el directorio si no existe y guardar el ZIP
        Storage::disk('local')->makeDirectory('imports/zip_temp');

        $storedPath = $request->file('archivo')->storeAs(
            'imports/zip_temp',
            uniqid('zip_', true) . '.zip',
            'local'
        );

        if (!$storedPath) {
            return $this->error('No se pudo almacenar el archivo ZIP temporalmente.', 500);
        }

        // Crear registro de seguimiento
        $importJob = ImportJob::create([
            'tipo'   => 'zip_historiales',
            'estado' => 'pendiente',
        ]);

        // Despachar job en background
        ProcessHistorialesZipJob::dispatch($importJob->id, $storedPath);

        return $this->success(
            ['job_id' => $importJob->id],
            'Importación iniciada. Consulta el estado con el job_id.',
            202
        );
    }

    /**
     * Consulta el estado de un import job.
     * GET /usuarios/estudiantes/import-status/{id}
     */
    public function importStatus(string $id): JsonResponse
    {
        $job = ImportJob::find($id);

        if (!$job) {
            return $this->notFound('Import job no encontrado');
        }

        return $this->success([
            'id'             => $job->id,
            'tipo'           => $job->tipo,
            'estado'         => $job->estado,
            'resultado'      => $job->resultado,
            'error_mensaje'  => $job->error_mensaje,
            'created_at'     => $job->created_at?->toISOString(),
            'updated_at'     => $job->updated_at?->toISOString(),
        ]);
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
