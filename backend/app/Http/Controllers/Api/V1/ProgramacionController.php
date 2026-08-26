<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Programacion\ImportProgramacionDTO;
use App\DTOs\Programacion\ProgramacionFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Programacion\ImportProgramacionHtmlRequest;
use App\Http\Requests\Programacion\ImportProgramacionRequest;
use App\Imports\ProgramacionCampusImport;
use App\Imports\ProgramacionHtmlImport;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\Programacion;
use App\Models\ProgramacionAcademica;
use App\Services\ImportarDiffProgramacionService;
use App\Services\ProgramacionService;
use App\Transformers\ProgramacionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Exception;

class ProgramacionController extends Controller
{
    public function __construct(
        protected ProgramacionService $service,
        protected ProgramacionTransformer $transformer,
        protected ImportarDiffProgramacionService $diffService
    ) {}

    /**
     * GET /programacion/grupos?periodo_id=xxx
     * Devuelve los grupos únicos de un periodo (para el filtro del frontend).
     */
    public function grupos(Request $request): JsonResponse
    {
        $periodoId = $request->query('periodo_id')
            ?? $this->service->getActivePeriodoId();

        if (!$periodoId) {
            return $this->success([], 'Sin periodo activo');
        }

        $grupos = ProgramacionAcademica::periodo($periodoId)
            ->select('programacion_secciones.grupo')
            ->whereNotNull('programacion_secciones.grupo')
            ->distinct()
            ->pluck('programacion_secciones.grupo')
            ->filter()
            ->sortBy(fn($g) => (int) preg_replace('/\D/', '', $g))
            ->values();

        return $this->success($grupos, 'Grupos del periodo');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            $user = $request->user();
            if ($user && $user->tipo_usuario === 'estudiante' && $user->escuela_id) {
                $data['escuela_id'] = $user->escuela_id;
                unset($data['ciclo']); // el ciclo para estudiante lo maneja paraMi()
                unset($data['area_id']); // el filtro de área no aplica para estudiantes
            }

            $dto    = ProgramacionFilterDTO::fromRequest($data);
            $result = $this->service->getPaginated($dto, $request, $user);

            // Marcar cursos equivalentes (de otra escuela) antes de transformar
            $escuelaId = ($user && $user->isEstudiante()) ? $user->escuela_id : null;
            $items = $this->transformer->collection(collect($result->items()), $escuelaId);

            return $this->paginated($items, $result, 'Lista de programación académica');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function show(string $id): JsonResponse
    {
        $programacion = $this->service->findById($id);

        if (!$programacion) {
            return $this->notFound('Programación no encontrada');
        }

        return $this->success($this->transformer->toArray($programacion));
    }

    public function import(ImportProgramacionRequest $request): JsonResponse
    {
        try {
            $dto = ImportProgramacionDTO::fromRequest(
                $request->file('file'),
                $request->periodo_id
            );

            $this->service->import($dto);

            return $this->success(null, 'Programación importada exitosamente');
        } catch (Exception $e) {
            return $this->error('Error al procesar el Excel: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Programaciones elegibles para el estudiante autenticado.
     */
    public function paraMi(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user || !$user->isEstudiante()) {
                return $this->error('Solo disponible para estudiantes.', 403);
            }

            $result    = $this->service->getElegiblesParaEstudiante($user, $request);
            $paginated = $result['paginated'];
            $items     = $this->transformer->collection(collect($paginated->items()));

            return response()->json([
                'success' => true,
                'message' => 'Cursos disponibles para ti',
                'data'    => [
                    'ciclo_actual'         => $result['ciclo_actual'],
                    'historial_registrado' => $result['historial_registrado'],
                    'items'                => $items,
                    'pagination'           => [
                        'current_page' => $paginated->currentPage(),
                        'last_page'    => $paginated->lastPage(),
                        'per_page'     => $paginated->perPage(),
                        'total'        => $paginated->total(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Importar programación desde reporte Excel del sistema Campus.
     * El encabezado está en la fila 8 y el ciclo ya viene como entero.
     * Marca automáticamente lleno_manual = true cuando n_inscritos >= capacidad.
     */
    public function importCampus(ImportProgramacionRequest $request): JsonResponse
    {
        try {
            $dto = ImportProgramacionDTO::fromRequest(
                $request->file('file'),
                $request->periodo_id
            );

            $resumen = $this->service->importCampus($dto);

            $msg = "Campus importado: {$resumen['actualizados']} actualizados, {$resumen['omitidos']} omitidos.";
            return $this->success($resumen, $msg);
        } catch (Exception $e) {
            return $this->error('Error al procesar el Excel Campus: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Devuelve TODA la programación del periodo activo sin filtro de escuela/plan.
     * Usado para el flujo "No encuentro mi curso" del estudiante.
     */
    public function todosParaSolicitud(Request $request): JsonResponse
    {
        try {
            $periodoId = $request->get('periodo_id') ?? $this->service->getActivePeriodoId();

            if (!$periodoId) {
                return $this->error('No hay periodo activo.', 422);
            }

            $page    = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 15);
            $search  = $request->get('search', '');

            // Obtener escuela del estudiante autenticado para excluir sus propios cursos
            $user      = $request->user();
            $escuelaId = $user?->escuela_id;

            $query = \App\Models\ProgramacionAcademica::periodo($periodoId)
                ->with(['curso', 'docente', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas'])
                ->join('cursos', 'cursos.id', '=', 'programacion_secciones.curso_id')
                ->select('programacion_secciones.*');

            // Solo mostrar cursos de OTRAS escuelas (no de la escuela propia del alumno)
            if ($escuelaId) {
                $query->whereNotExists(function ($q) use ($escuelaId) {
                    $q->from('programacion_escuelas')
                      ->whereColumn('programacion_escuelas.programacion_id', 'programacion_secciones.id')
                      ->where('programacion_escuelas.escuela_id', $escuelaId);
                });
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('cursos.nombre', 'LIKE', "%{$search}%")
                      ->orWhere('cursos.codigo', 'LIKE', "%{$search}%");
                });
            }

            $query->orderByRaw('cursos.nombre ASC');

            $paginated = $query->paginate($perPage, ['*'], 'page', $page);

            $items = $this->transformer->collection(collect($paginated->items()));

            return response()->json([
                'success' => true,
                'message' => 'Todos los cursos del periodo',
                'data'    => [
                    'items'      => $items,
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page'    => $paginated->lastPage(),
                        'per_page'     => $paginated->perPage(),
                        'total'        => $paginated->total(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Importar programación desde la Matriz de Programación Académica (CSV/Excel).
     * Borra la programación existente del periodo y carga desde la matriz.
     */
    public function importMatriz(ImportProgramacionRequest $request): JsonResponse
    {
        try {
            $dto = ImportProgramacionDTO::fromRequest(
                $request->file('file'),
                $request->periodo_id
            );

            $resumen = $this->service->importMatriz($dto);

            $msg = "Matriz importada: {$resumen['importados']} secciones cargadas, {$resumen['omitidos']} omitidas.";
            return $this->success($resumen, $msg);
        } catch (Exception $e) {
            return $this->error('Error al procesar la matriz: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Importar programación desde reporte HTML del SIGA
     */
    public function importHtml(ImportProgramacionHtmlRequest $request): JsonResponse
    {
        try {
            $periodoId = $request->periodo_id ?? $this->service->getActivePeriodoId();

            if (!$periodoId) {
                return $this->error('No hay periodo activo ni se especificó uno.', 422);
            }

            $importer = new ProgramacionHtmlImport($periodoId);
            $importer->import($request->file('file')->getPathname());

            return $this->success(
                $importer->getResumen(),
                'Programación importada desde reporte SIGA'
            );
        } catch (Exception $e) {
            return $this->error('Error al procesar el archivo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Secciones existentes de un curso en un periodo (para el formulario de creación).
     */
    public function seccionesDelCurso(Request $request): JsonResponse
    {
        $cursoId   = $request->get('curso_id');
        $periodoId = $request->get('periodo_id') ?? $this->service->getActivePeriodoId();

        if (!$cursoId || !$periodoId) {
            return $this->success([]);
        }

        $secciones = ProgramacionAcademica::periodo($periodoId)
            ->where('programacion_secciones.curso_id', $cursoId)
            ->with(['docente', 'aulaRelacion', 'grupoHorario', 'escuelaProgramada'])
            ->orderByRaw('CAST(IFNULL(seccion, 0) AS UNSIGNED) ASC')
            ->get()
            ->map(fn($p) => [
                'id'          => $p->id,
                'seccion'     => $p->seccion,
                'grupo'       => $p->grupoHorario?->nombre ?? $p->grupo,
                'docente'     => $p->docente?->nombre_completo,
                'aula'        => $p->aula ?? $p->aulaRelacion?->nombre,
                'capacidad'   => $p->capacidad,
                'n_inscritos' => $p->n_inscritos,
            ]);

        return $this->success($secciones);
    }

    /**
     * Crear programación académica manual (curso por curso)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periodo_id'                   => 'required|uuid|exists:periodos,id',
            'curso_id'                     => 'required|uuid|exists:cursos,id',
            'escuelas'                     => 'required|array|min:1',
            'escuelas.*'                   => 'uuid|exists:escuelas,id',
            'escuela_programada_id'        => 'nullable|uuid|exists:escuelas,id',
            'secciones'                    => 'required|array|min:1',
            'secciones.*.seccion'          => 'nullable|string|max:10',
            'secciones.*.grupo_horario_id' => 'nullable|uuid|exists:grupos_horario,id',
            'secciones.*.aula_id'          => 'nullable|uuid|exists:aulas,id',
            'secciones.*.docente_id'       => 'nullable|uuid|exists:docentes,id',
            'secciones.*.capacidad'        => 'required|integer|min:1|max:500',
        ]);

        try {
            $curso   = Curso::findOrFail($data['curso_id']);
            $created = [];

            // Buscar la programación maestra publicada para este periodo
            $progMaestro = Programacion::where('periodo_id', $data['periodo_id'])
                ->where('estado', 'publicado')
                ->first();

            if (!$progMaestro) {
                return $this->error('No existe una programación publicada para el periodo indicado.', 422);
            }

            // Calcular el máximo número de sección existente para continuar la numeración
            $maxSeccionExistente = ProgramacionAcademica::where('programacion_id', $progMaestro->id)
                ->where('programacion_secciones.curso_id', $data['curso_id'])
                ->whereNotNull('programacion_secciones.seccion')
                ->get()
                ->map(fn($p) => is_numeric($p->seccion) ? (int) $p->seccion : 0)
                ->max() ?? 0;

            // Verificar conflictos antes de crear
            $conflictos = [];
            foreach ($data['secciones'] as $index => $seccionData) {
                $grupoId = $seccionData['grupo_horario_id'] ?? null;
                $aulaId  = $seccionData['aula_id'] ?? null;

                if (!$grupoId || !$aulaId) continue;

                $ocupadaBD = ProgramacionAcademica::where('programacion_id', $progMaestro->id)
                    ->where('grupo_horario_id', $grupoId)
                    ->where('aula_id', $aulaId)
                    ->with(['curso', 'grupoHorario'])
                    ->first();

                if ($ocupadaBD) {
                    $grupoNombreConflicto = $ocupadaBD->grupoHorario?->nombre ?? $grupoId;
                    $conflictos[] = "Sección " . ($index + 1) . ": el {$grupoNombreConflicto} en esa aula ya está ocupado por \"{$ocupadaBD->curso?->nombre}\"";
                    continue;
                }

                foreach (array_slice($data['secciones'], 0, $index) as $prev) {
                    if (($prev['grupo_horario_id'] ?? null) === $grupoId && ($prev['aula_id'] ?? null) === $aulaId) {
                        $grupo = GrupoHorario::find($grupoId);
                        $conflictos[] = "Sección " . ($index + 1) . ": repite el mismo grupo y aula que otra sección en este formulario ({$grupo?->nombre})";
                        break;
                    }
                }
            }

            if (!empty($conflictos)) {
                return $this->error('Conflicto de horario: ' . implode('. ', $conflictos), 422);
            }

            foreach ($data['secciones'] as $index => $seccionData) {
                $grupoNombre = null;
                if (!empty($seccionData['grupo_horario_id'])) {
                    $grupoNombre = GrupoHorario::find($seccionData['grupo_horario_id'])?->nombre;
                }

                $id    = (string) Str::uuid();
                $clave = 'M' . strtoupper(substr(str_replace('-', '', $id), 0, 8));

                // Usar sección manual o auto-calcular a partir de las existentes
                $seccionNum = isset($seccionData['seccion']) && $seccionData['seccion'] !== ''
                    ? $seccionData['seccion']
                    : (string) ($maxSeccionExistente + $index + 1);

                $prog = ProgramacionAcademica::create([
                    'id'                   => $id,
                    'curso_id'             => $data['curso_id'],
                    'programacion_id'      => $progMaestro->id,
                    'docente_id'           => $seccionData['docente_id'] ?? null,
                    'aula_id'              => $seccionData['aula_id'] ?? null,
                    'grupo_horario_id'     => $seccionData['grupo_horario_id'] ?? null,
                    'clave'                => $clave,
                    'grupo'                => $grupoNombre,
                    'seccion'              => $seccionNum,
                    'capacidad'            => $seccionData['capacidad'],
                    'n_inscritos'          => 0,
                    'lleno_manual'         => false,
                    'escuela_programada_id' => $data['escuela_programada_id'] ?? null,
                ]);

                $prog->escuelas()->sync($data['escuelas']);
                $created[] = $prog->id;
            }

            return $this->success(
                ['creadas' => count($created)],
                count($created) . ' sección(es) de "' . $curso->nombre . '" creadas exitosamente'
            );
        } catch (Exception $e) {
            return $this->error('Error al crear programación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar una sección de programación académica
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $programacion = $this->service->findById($id);

        if (!$programacion) {
            return $this->notFound('Programación no encontrada');
        }

        $data = $request->validate([
            'grupo_horario_id'    => 'nullable|uuid|exists:grupos_horario,id',
            'aula_id'             => 'nullable|uuid|exists:aulas,id',
            'docente_id'          => 'nullable|uuid|exists:docentes,id',
            'capacidad'           => 'required|integer|min:1|max:500',
            'n_inscritos'         => 'nullable|integer|min:0|max:500',
            'seccion'             => 'nullable|string|max:10',
            'escuelas'            => 'nullable|array',
            'escuelas.*'          => 'uuid|exists:escuelas,id',
            'escuela_programada_id' => 'nullable|uuid|exists:escuelas,id',
        ]);

        try {
            // Verificar conflicto de grupo+aula (excluyendo el mismo registro)
            if (!empty($data['grupo_horario_id']) && !empty($data['aula_id'])) {
                $conflicto = ProgramacionAcademica::where('programacion_id', $programacion->programacion_id)
                    ->where('grupo_horario_id', $data['grupo_horario_id'])
                    ->where('aula_id', $data['aula_id'])
                    ->where('id', '!=', $id)
                    ->with(['curso'])
                    ->first();

                if ($conflicto) {
                    return $this->error(
                        "Conflicto: ese grupo y aula ya están ocupados por \"{$conflicto->curso?->nombre}\"",
                        422
                    );
                }
            }

            $grupoNombre = null;
            if (!empty($data['grupo_horario_id'])) {
                $grupoNombre = GrupoHorario::find($data['grupo_horario_id'])?->nombre;
            }

            $nInscritos = array_key_exists('n_inscritos', $data) && $data['n_inscritos'] !== null
                ? (int) $data['n_inscritos']
                : $programacion->n_inscritos;

            $programacion->update([
                'grupo_horario_id'    => $data['grupo_horario_id'],
                'aula_id'             => $data['aula_id'],
                'docente_id'          => $data['docente_id'],
                'capacidad'           => $data['capacidad'],
                'n_inscritos'         => $nInscritos,
                'lleno_manual'        => $nInscritos >= (int) $data['capacidad'],
                'grupo'               => $grupoNombre,
                'seccion'             => array_key_exists('seccion', $data) ? $data['seccion'] : $programacion->seccion,
                'escuela_programada_id' => $data['escuela_programada_id'] ?? $programacion->escuela_programada_id,
            ]);

            if (isset($data['escuelas'])) {
                $programacion->escuelas()->sync($data['escuelas']);
            }

            $programacion->load(['curso', 'docente', 'programacion.periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas', 'escuelaProgramada']);

            return $this->success(
                $this->transformer->toArray($programacion),
                'Programación actualizada correctamente'
            );
        } catch (Exception $e) {
            return $this->error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar una sección de programación académica
     */
    public function destroy(string $id): JsonResponse
    {
        $programacion = $this->service->findById($id);

        if (!$programacion) {
            return $this->notFound('Programación no encontrada');
        }

        try {
            $this->service->delete($id);
            return $this->success(null, 'Programación eliminada correctamente');
        } catch (Exception $e) {
            return $this->error('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Exportar programación a Excel con filtros actuales
     */
    public function export(Request $request): mixed
    {
        try {
            $periodoId  = $request->get('periodo_id') ?? $this->service->getActivePeriodoId();
            $search     = $request->get('search', '');
            $escuelaId  = $request->get('escuela_id');
            $conHorario = (bool) $request->get('con_horario', false);

            if (!$periodoId) {
                return $this->error('No hay periodo activo ni se especificó uno.', 422);
            }

            $ciclo  = $request->get('ciclo') ? (int) $request->get('ciclo') : null;
            $areaId = $request->get('area_id');
            $items  = $this->service->getAllForExport($periodoId, $search ?: null, $escuelaId, $ciclo, $areaId);

            // Cabeceras base
            $headers = ['CÓDIGO', 'CURSO', 'CICLO', 'ESCUELA', 'DEPARTAMENTO', 'GRUPO', 'SEC', 'AULA', 'DOCENTE', 'CAPACIDAD', 'INSCRITOS', 'ESTADO', 'CLAVE'];

            // Días para columnas de horario (Lun-Vie)
            $diasHorario = ['lunes' => 'LUN', 'martes' => 'MAR', 'miercoles' => 'MIÉ', 'jueves' => 'JUE', 'viernes' => 'VIE'];
            if ($conHorario) {
                foreach ($diasHorario as $label) {
                    $headers[] = $label;
                }
            }

            $colCount = count($headers);
            // Para más de 26 columnas usamos \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex
            $lastColIdx = $colCount; // 1-based

            // Crear Excel con PhpSpreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Programación');

            foreach ($headers as $i => $h) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                $sheet->setCellValue("{$colLetter}1", $h);
                $sheet->getStyle("{$colLetter}1")->getFont()->setBold(true);
                $isHorarioCol = $conHorario && $i >= 13; // columnas de día
                $sheet->getStyle("{$colLetter}1")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($isHorarioCol ? 'FF0F766E' : 'FF6366F1');
                $sheet->getStyle("{$colLetter}1")->getFont()->getColor()->setARGB('FFFFFFFF');
            }

            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIdx);

            // Datos
            $row = 2;
            foreach ($items as $prog) {
                $aulaNombre     = $prog->aula ?? $prog->aulaRelacion?->nombre ?? '';
                $escuelaNombres = $prog->escuelas->isNotEmpty()
                    ? $prog->escuelas->pluck('nombre_corto')->implode(', ')
                    : '';
                $departamento   = $prog->curso?->area?->nombre ?? '';
                $estado = $prog->estaLleno()
                    ? ($prog->lleno_manual ? 'LLENO (Manual)' : 'LLENO')
                    : 'DISPONIBLE';

                $sheet->setCellValue("A{$row}", $prog->curso?->codigo ?? '');
                $sheet->setCellValue("B{$row}", $prog->curso?->nombre ?? '');
                $sheet->setCellValue("C{$row}", $prog->ciclo ?? '');
                $sheet->setCellValue("D{$row}", $escuelaNombres);
                $sheet->setCellValue("E{$row}", $departamento);
                $sheet->setCellValue("F{$row}", $prog->grupo ?? '');
                $sheet->setCellValue("G{$row}", $prog->seccion ?? '');
                $sheet->setCellValue("H{$row}", $aulaNombre);
                $sheet->setCellValue("I{$row}", $prog->docente?->nombre_completo ?? 'POR ASIGNAR');
                $sheet->setCellValue("J{$row}", $prog->capacidad ?? 0);
                $sheet->setCellValue("K{$row}", $prog->n_inscritos ?? 0);
                $sheet->setCellValue("L{$row}", $estado);
                $sheet->setCellValue("M{$row}", $prog->clave ?? '');

                // Columnas de horario por día
                if ($conHorario) {
                    $detalles = $prog->grupoHorario?->detalles ?? collect();
                    $colOffset = 14; // columna N = 14 (1-based)
                    foreach (array_keys($diasHorario) as $dia) {
                        $rangos = $detalles
                            ->where('dia_semana', $dia)
                            ->map(fn($d) => substr($d->hora_inicio, 0, 5) . '-' . substr($d->hora_fin, 0, 5))
                            ->implode(' · ');
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colOffset);
                        $sheet->setCellValue("{$colLetter}{$row}", $rangos);
                        $colOffset++;
                    }
                }

                if ($prog->estaLleno()) {
                    $sheet->getStyle("A{$row}:{$lastColLetter}{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEF2F2');
                }

                $row++;
            }

            // Autofit
            for ($i = 1; $i <= $lastColIdx; $i++) {
                $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
            }

            $tmpFile = tempnam(sys_get_temp_dir(), 'prog_export_') . '.xlsx';
            $writer  = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($tmpFile);

            return response()->download($tmpFile, 'programacion_export.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return $this->error('Error al exportar: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Vista previa del diff entre archivo Excel/CSV y la programación actual del periodo.
     * No modifica la base de datos.
     */
    public function importarDiffPreview(ImportProgramacionRequest $request): JsonResponse
    {
        try {
            $diff = $this->diffService->preview(
                $request->file('file'),
                $request->periodo_id
            );

            return $this->success($diff, 'Análisis de diferencias generado correctamente');
        } catch (Exception $e) {
            return $this->error('Error al analizar el archivo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Aplica el diff entre archivo Excel/CSV y la programación actual del periodo.
     */
    public function importarDiffAplicar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'       => 'required|mimes:xlsx,xls,csv',
            'periodo_id' => 'required|uuid|exists:periodos,id',
            'motivo'     => 'required|string|max:500',
        ]);

        try {
            $resumen = $this->diffService->aplicar(
                $request->file('file'),
                $data['periodo_id'],
                $data['motivo'],
                (string) $request->user()?->id
            );

            $aplicadas = $resumen['aplicadas'];
            $msg = "Diff aplicado: {$aplicadas['nuevas']} nuevas, {$aplicadas['eliminadas']} cerradas, "
                 . "{$aplicadas['reabiertas']} reabiertas, "
                 . "{$aplicadas['cambios_aula']} cambios aula, {$aplicadas['cambios_grupo']} cambios grupo, "
                 . "{$aplicadas['cambios_aula_y_grupo']} cambios aula+grupo, "
                 . "{$aplicadas['cambios_cupo']} cambios de cupo.";

            return $this->success($resumen, $msg);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (Exception $e) {
            return $this->error('Error al aplicar los cambios: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar TODA la programación de un periodo (solo developer)
     */
    public function destroyPeriodo(string $periodoId): JsonResponse
    {
        try {
            $resultado = $this->service->deleteByPeriodo($periodoId);
            $msg = "Se eliminaron {$resultado['programacion']} secciones";
            if ($resultado['inscripciones'] > 0) $msg .= ", {$resultado['inscripciones']} inscripciones";
            if ($resultado['solicitudes'] > 0)   $msg .= " y {$resultado['solicitudes']} solicitudes";
            return $this->success($resultado, $msg);
        } catch (Exception $e) {
            return $this->error('Error al eliminar: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Marcar/desmarcar un curso como lleno manualmente
     */
    public function toggleLleno(string $id): JsonResponse
    {
        $programacion = $this->service->toggleLlenoManual($id);

        if (!$programacion) {
            return $this->notFound('Programación no encontrada');
        }

        $mensaje = $programacion->lleno_manual
            ? 'Curso marcado como lleno'
            : 'Curso desmarcado como lleno';

        return $this->success($this->transformer->toArray($programacion), $mensaje);
    }

    /**
     * Marcar/desmarcar como lleno TODAS las secciones activas de un periodo de una sola vez
     * (uso típico: tras el cierre de fechas de inscripción, para que los alumnos deban
     * pasar por el flujo de solicitud aunque el curso técnicamente tenga cupo, evitando colas).
     */
    public function marcarLlenoPorPeriodo(Request $request, string $periodoId): JsonResponse
    {
        $request->validate(['lleno' => 'required|boolean']);

        try {
            $lleno = $request->boolean('lleno');
            $actualizadas = $this->service->marcarLlenoPorPeriodo($periodoId, $lleno);
            $msg = $lleno
                ? "{$actualizadas} sección(es) marcadas como llenas"
                : "{$actualizadas} sección(es) desmarcadas como llenas";

            return $this->success(['actualizadas' => $actualizadas], $msg);
        } catch (Exception $e) {
            return $this->error('Error al actualizar: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Descargar plantilla de ejemplo para importación
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        $templatePath = storage_path('app/templates/programacion_template.xlsx');

        if (!file_exists($templatePath)) {
            $this->createTemplate($templatePath);
        }

        return response()->download($templatePath, 'plantilla_programacion.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function createTemplate(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Programación');

        $headers = [
            'A1' => 'CODIGO', 'B1' => 'NOMBRE_DEL_CURSO', 'C1' => 'AREA',
            'D1' => 'DOCENTE', 'E1' => 'CLAVE', 'F1' => 'GRP',
            'G1' => 'SEC', 'H1' => 'AULA', 'I1' => 'N_ACTA',
            'J1' => 'CAP', 'K1' => 'N_INSCRITOS',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
        }

        $example = [
            'A2' => 'MAT101', 'B2' => 'CALCULO I', 'C2' => 'MATEMATICAS',
            'D2' => 'GARCIA LOPEZ JUAN', 'E2' => '12345', 'F2' => 'A',
            'G2' => '1', 'H2' => 'AULA-101', 'I2' => '001', 'J2' => '40', 'K2' => '35',
        ];

        foreach ($example as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }
}
