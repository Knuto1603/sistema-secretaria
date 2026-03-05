<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Programacion\ImportProgramacionDTO;
use App\DTOs\Programacion\ProgramacionFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Programacion\ImportProgramacionRequest;
use App\Services\ProgramacionService;
use App\Transformers\ProgramacionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

            // Si el usuario es estudiante, inyectar su escuela_id para filtrar por plan
            $user = $request->user();
            if ($user && $user->tipo_usuario === 'estudiante' && $user->escuela_id) {
                $data['escuela_id'] = $user->escuela_id;
            }

            $dto = ProgramacionFilterDTO::fromRequest($data);
            $result = $this->service->getPaginated($dto, $request);

            $items = $this->transformer->collection(collect($result->items()));

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

        // Si no existe el archivo, lo creamos
        if (!file_exists($templatePath)) {
            $this->createTemplate($templatePath);
        }

        return response()->download($templatePath, 'plantilla_programacion.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Crear archivo de plantilla Excel
     */
    private function createTemplate(string $path): void
    {
        // Asegurar que existe el directorio
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Crear usando PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Programación');

        // Encabezados
        $headers = [
            'A1' => 'CODIGO',
            'B1' => 'NOMBRE_DEL_CURSO',
            'C1' => 'AREA',
            'D1' => 'DOCENTE',
            'E1' => 'CLAVE',
            'F1' => 'GRP',
            'G1' => 'SEC',
            'H1' => 'AULA',
            'I1' => 'N_ACTA',
            'J1' => 'CAP',
            'K1' => 'N_INSCRITOS',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');
        }

        // Fila de ejemplo
        $example = [
            'A2' => 'MAT101',
            'B2' => 'CALCULO I',
            'C2' => 'MATEMATICAS',
            'D2' => 'GARCIA LOPEZ JUAN',
            'E2' => '12345',
            'F2' => 'A',
            'G2' => '1',
            'H2' => 'AULA-101',
            'I2' => '001',
            'J2' => '40',
            'K2' => '35',
        ];

        foreach ($example as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Ajustar ancho de columnas
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Guardar
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);
    }
}
