<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentoModificacionArea;
use App\Models\GeneracionModificacion;
use App\Services\GeneradorDocumentoModificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class GeneracionModificacionController extends Controller
{
    public function __construct(
        protected GeneradorDocumentoModificacionService $service
    ) {}

    /**
     * GET /modificaciones/generaciones
     * Historial de generaciones del periodo.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['periodo_id' => ['nullable', 'uuid', 'exists:periodos,id']]);

        $query = GeneracionModificacion::with(['documentos.area', 'generadoPor:id,name', 'periodo:id,nombre'])
            ->orderByDesc('generado_at');

        if ($request->filled('periodo_id')) {
            $query->where('periodo_id', $request->periodo_id);
        }

        return $this->success(
            $query->get()->map(fn ($g) => $this->formatGeneracion($g))->values(),
            'Historial de generaciones de documentos'
        );
    }

    /**
     * POST /modificaciones/generar-preview
     * Previsualiza los documentos que se generarían sin crearlos.
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periodo_id'  => ['required', 'uuid', 'exists:periodos,id'],
            'fecha_desde' => ['required', 'date'],
            'fecha_hasta' => ['required', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $preview = $this->service->preview($data['periodo_id'], $data['fecha_desde'], $data['fecha_hasta']);

        return $this->success($preview, 'Vista previa de documentos a generar');
    }

    /**
     * POST /modificaciones/generar
     * Genera los documentos, los descarga como ZIP y marca modificaciones como documentadas.
     */
    public function generar(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'periodo_id'   => ['required', 'uuid', 'exists:periodos,id'],
            'fecha_desde'  => ['required', 'date'],
            'fecha_hasta'  => ['required', 'date', 'after_or_equal:fecha_desde'],
            'numero_oficio'=> ['required', 'string', 'max:100'],
        ]);

        $generacion = $this->service->generar(
            $data['periodo_id'],
            $data['fecha_desde'],
            $data['fecha_hasta'],
            $data['numero_oficio'],
            $request->user()
        );

        $zipPath = $this->service->generarZip($generacion);

        return response()->download(
            $zipPath,
            "modificaciones_{$generacion->numero_oficio}.zip",
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    /**
     * GET /modificaciones/generaciones/{id}/zip
     * Descarga el ZIP de una generación anterior.
     */
    public function descargarZip(string $id): BinaryFileResponse
    {
        $generacion = GeneracionModificacion::with('documentos')->findOrFail($id);

        $zipPath = $this->service->generarZip($generacion);

        return response()->download(
            $zipPath,
            "modificaciones_{$generacion->numero_oficio}.zip",
            ['Content-Type' => 'application/zip']
        )->deleteFileAfterSend(true);
    }

    /**
     * GET /modificaciones/generaciones/{generacionId}/documentos/{areaId}/descargar
     * Descarga un documento individual.
     */
    public function descargarDocumento(string $generacionId, string $areaId): BinaryFileResponse
    {
        $doc = DocumentoModificacionArea::where('generacion_id', $generacionId)
            ->where('area_id', $areaId)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($doc->ruta), 404, 'Archivo no encontrado.');

        return response()->download(
            Storage::disk('local')->path($doc->ruta),
            $doc->nombre_archivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }

    /**
     * DELETE /modificaciones/generaciones/{id}
     * Elimina una generación y sus archivos físicos.
     */
    public function eliminar(string $id): JsonResponse
    {
        $generacion = GeneracionModificacion::with('documentos')->findOrFail($id);

        $carpeta = "modificaciones/{$id}";
        if (Storage::disk('local')->exists($carpeta)) {
            Storage::disk('local')->deleteDirectory($carpeta);
        }

        DB::transaction(function () use ($generacion) {
            foreach ($generacion->documentos as $doc) {
                $doc->modificaciones()->detach();
                $doc->delete();
            }
            $generacion->delete();
        });

        return $this->success(null, 'Generación eliminada correctamente.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatGeneracion(GeneracionModificacion $g): array
    {
        return [
            'id'               => $g->id,
            'numero_oficio'    => $g->numero_oficio,
            'fecha_desde'      => $g->fecha_desde?->toDateString(),
            'fecha_hasta'      => $g->fecha_hasta?->toDateString(),
            'generado_at'      => $g->generado_at?->toIso8601String(),
            'total_documentos' => $g->total_documentos,
            'periodo'          => $g->periodo ? ['id' => $g->periodo->id, 'nombre' => $g->periodo->nombre] : null,
            'generado_por'     => $g->generadoPor?->name,
            'documentos'       => $g->documentos->map(fn ($d) => [
                'id'                  => $d->id,
                'area_id'             => $d->area_id,
                'area_nombre'         => $d->area?->nombre ?? '',
                'tipo_documento'      => $d->tipo_documento,
                'nombre_archivo'      => $d->nombre_archivo,
                'modificaciones_count'=> $d->modificaciones_count,
            ])->toArray(),
        ];
    }
}
