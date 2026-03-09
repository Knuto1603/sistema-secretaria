<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Programacion\ImportProgramacionDTO;
use App\DTOs\Programacion\ProgramacionFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Programacion\ImportProgramacionHtmlRequest;
use App\Http\Requests\Programacion\ImportProgramacionRequest;
use App\Imports\ProgramacionHtmlImport;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\ProgramacionAcademica;
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
        protected ProgramacionTransformer $transformer
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            $user = $request->user();
            if ($user && $user->tipo_usuario === 'estudiante' && $user->escuela_id) {
                $data['escuela_id'] = $user->escuela_id;
                unset($data['ciclo']); // el ciclo para estudiante lo maneja paraMi()
            }

            $dto    = ProgramacionFilterDTO::fromRequest($data);
            $result = $this->service->getPaginated($dto, $request);
            $items  = $this->transformer->collection(collect($result->items()));

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
     * Crear programación académica manual (curso por curso)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periodo_id'                   => 'required|uuid|exists:periodos,id',
            'curso_id'                     => 'required|uuid|exists:cursos,id',
            'escuelas'                     => 'required|array|min:1',
            'escuelas.*'                   => 'uuid|exists:escuelas,id',
            'secciones'                    => 'required|array|min:1',
            'secciones.*.grupo_horario_id' => 'nullable|uuid|exists:grupos_horario,id',
            'secciones.*.aula_id'          => 'nullable|uuid|exists:aulas,id',
            'secciones.*.docente_id'       => 'nullable|uuid|exists:docentes,id',
            'secciones.*.capacidad'        => 'required|integer|min:1|max:500',
        ]);

        try {
            $curso   = Curso::findOrFail($data['curso_id']);
            $created = [];

            // Verificar conflictos antes de crear
            $conflictos = [];
            foreach ($data['secciones'] as $index => $seccionData) {
                $grupoId = $seccionData['grupo_horario_id'] ?? null;
                $aulaId  = $seccionData['aula_id'] ?? null;

                if (!$grupoId || !$aulaId) continue;

                $ocupadaBD = ProgramacionAcademica::where('periodo_id', $data['periodo_id'])
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

                $prog = ProgramacionAcademica::create([
                    'id'               => $id,
                    'curso_id'         => $data['curso_id'],
                    'periodo_id'       => $data['periodo_id'],
                    'docente_id'       => $seccionData['docente_id'] ?? null,
                    'aula_id'          => $seccionData['aula_id'] ?? null,
                    'grupo_horario_id' => $seccionData['grupo_horario_id'] ?? null,
                    'clave'            => $clave,
                    'grupo'            => $grupoNombre,
                    'seccion'          => (string) ($index + 1),
                    'capacidad'        => $seccionData['capacidad'],
                    'n_inscritos'      => 0,
                    'lleno_manual'     => false,
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
            'grupo_horario_id' => 'nullable|uuid|exists:grupos_horario,id',
            'aula_id'          => 'nullable|uuid|exists:aulas,id',
            'docente_id'       => 'nullable|uuid|exists:docentes,id',
            'capacidad'        => 'required|integer|min:1|max:500',
            'escuelas'         => 'nullable|array',
            'escuelas.*'       => 'uuid|exists:escuelas,id',
        ]);

        try {
            // Verificar conflicto de grupo+aula (excluyendo el mismo registro)
            if (!empty($data['grupo_horario_id']) && !empty($data['aula_id'])) {
                $conflicto = ProgramacionAcademica::where('periodo_id', $programacion->periodo_id)
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

            $programacion->update([
                'grupo_horario_id' => $data['grupo_horario_id'],
                'aula_id'          => $data['aula_id'],
                'docente_id'       => $data['docente_id'],
                'capacidad'        => $data['capacidad'],
                'grupo'            => $grupoNombre,
            ]);

            if (isset($data['escuelas'])) {
                $programacion->escuelas()->sync($data['escuelas']);
            }

            $programacion->load(['curso', 'docente', 'periodo', 'aulaRelacion.pabellon', 'grupoHorario.detalles', 'escuelas']);

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
            $periodoId = $request->get('periodo_id') ?? $this->service->getActivePeriodoId();
            $search    = $request->get('search', '');
            $escuelaId = $request->get('escuela_id');

            if (!$periodoId) {
                return $this->error('No hay periodo activo ni se especificó uno.', 422);
            }

            $ciclo = $request->get('ciclo') ? (int) $request->get('ciclo') : null;
            $items = $this->service->getAllForExport($periodoId, $search ?: null, $escuelaId, $ciclo);

            // Crear Excel con PhpSpreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Programación');

            // Cabeceras
            $headers = ['CÓDIGO', 'CURSO', 'GRUPO', 'SEC', 'AULA', 'DOCENTE', 'CAPACIDAD', 'INSCRITOS', 'ESTADO', 'CLAVE'];
            foreach ($headers as $i => $h) {
                $col = chr(65 + $i);
                $sheet->setCellValue("{$col}1", $h);
                $sheet->getStyle("{$col}1")->getFont()->setBold(true);
                $sheet->getStyle("{$col}1")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF6366F1');
                $sheet->getStyle("{$col}1")->getFont()->getColor()->setARGB('FFFFFFFF');
            }

            // Datos
            $row = 2;
            foreach ($items as $prog) {
                $aulaNombre = $prog->aula; // texto importado
                if (!$aulaNombre && $prog->aula_id) {
                    $aulaNombre = $prog->aulaRelacion?->nombre ?? '';
                }

                $estado = $prog->estaLleno()
                    ? ($prog->lleno_manual ? 'LLENO (Manual)' : 'LLENO')
                    : 'DISPONIBLE';

                $sheet->setCellValue("A{$row}", $prog->curso?->codigo ?? '');
                $sheet->setCellValue("B{$row}", $prog->curso?->nombre ?? '');
                $sheet->setCellValue("C{$row}", $prog->grupo ?? '');
                $sheet->setCellValue("D{$row}", $prog->seccion ?? '');
                $sheet->setCellValue("E{$row}", $aulaNombre);
                $sheet->setCellValue("F{$row}", $prog->docente?->nombre_completo ?? 'POR ASIGNAR');
                $sheet->setCellValue("G{$row}", $prog->capacidad ?? 0);
                $sheet->setCellValue("H{$row}", $prog->n_inscritos ?? 0);
                $sheet->setCellValue("I{$row}", $estado);
                $sheet->setCellValue("J{$row}", $prog->clave ?? '');

                if ($prog->estaLleno()) {
                    $sheet->getStyle("A{$row}:J{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFEF2F2');
                }

                $row++;
            }

            // Autofit
            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
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
