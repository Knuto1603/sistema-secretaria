<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BorradorProgramacion;
use App\Models\DocumentoArea;
use App\Models\GeneracionDocumento;
use App\Services\GeneradorOficioService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class GeneradorDocumentosController extends Controller
{
    public function __construct(private GeneradorOficioService $service) {}

    /**
     * Lista cursos sin área asignada para el período del borrador.
     */
    public function cursosSinArea(string $borradorId): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($borradorId);
        $sinArea  = $this->service->cursosSinArea($borrador);

        return $this->success($sinArea, 'Cursos sin área asignada');
    }

    /**
     * Genera los documentos Word para todos los departamentos.
     */
    public function generar(Request $request, string $borradorId): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($borradorId);

        if ($borrador->estado !== 'publicado') {
            return $this->error('Solo se pueden generar documentos de borradores publicados.', 422);
        }

        $data = $request->validate([
            'numero_oficio'  => 'required|string|max:100',
            'semestre_texto' => 'required|string|max:50',
        ]);

        try {
            $generacion = $this->service->generar(
                $borrador,
                $data['numero_oficio'],
                $data['semestre_texto'],
                $request->user()
            );

            return $this->success(
                $this->formatGeneracion($generacion),
                "Se generaron {$generacion->total_documentos} documento(s) correctamente."
            );
        } catch (\Throwable $e) {
            \Log::error('Error generando documentos', [
                'borrador_id' => $borradorId,
                'user_id'     => $request->user()?->id,
                'exception'   => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            return $this->error('Error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lista el historial de generaciones de un borrador.
     */
    public function generaciones(string $borradorId): JsonResponse
    {
        BorradorProgramacion::findOrFail($borradorId);

        $generaciones = GeneracionDocumento::where('borrador_id', $borradorId)
            ->with(['documentos.area', 'generadoPor', 'periodo'])
            ->orderByDesc('generado_at')
            ->get()
            ->map(fn($g) => $this->formatGeneracion($g));

        return $this->success($generaciones, 'Historial de generaciones');
    }

    /**
     * Descarga un documento individual.
     */
    public function descargar(string $generacionId, string $areaId): BinaryFileResponse
    {
        $doc = DocumentoArea::where('generacion_id', $generacionId)
            ->where('area_id', $areaId)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($doc->ruta), 404, 'Archivo no encontrado');

        return response()->download(
            Storage::disk('local')->path($doc->ruta),
            $doc->nombre_archivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }

    /**
     * Descarga todos los documentos de una generación como ZIP.
     */
    public function descargarTodos(string $generacionId): BinaryFileResponse
    {
        $generacion = GeneracionDocumento::with('documentos')->findOrFail($generacionId);

        $zipPath = Storage::disk('local')->path("documentos/temp_{$generacionId}.zip");

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($generacion->documentos as $doc) {
            if (Storage::disk('local')->exists($doc->ruta)) {
                $zip->addFile(Storage::disk('local')->path($doc->ruta), $doc->nombre_archivo);
            }
        }

        $zip->close();

        return response()->download($zipPath, "oficios_{$generacion->numero_oficio}.zip")
            ->deleteFileAfterSend(true);
    }

    /**
     * Elimina una generación y sus archivos físicos.
     */
    public function eliminar(string $generacionId): JsonResponse
    {
        $generacion = GeneracionDocumento::with('documentos')->findOrFail($generacionId);

        $carpeta = "documentos/{$generacion->borrador_id}/{$generacionId}";
        if (Storage::disk('local')->exists($carpeta)) {
            Storage::disk('local')->deleteDirectory($carpeta);
        }

        $generacion->documentos()->delete();
        $generacion->delete();

        return $this->success(null, 'Generación eliminada correctamente.');
    }

    // ─── Formato de respuesta ─────────────────────────────────────────────────

    private function formatGeneracion(GeneracionDocumento $g): array
    {
        return [
            'id'               => $g->id,
            'numero_oficio'    => $g->numero_oficio,
            'semestre_texto'   => $g->semestre_texto,
            'generado_at'      => $g->generado_at?->toISOString(),
            'total_documentos' => $g->total_documentos,
            'periodo'          => $g->periodo ? ['id' => $g->periodo->id, 'nombre' => $g->periodo->nombre] : null,
            'generado_por'     => $g->generadoPor ? $g->generadoPor->name : null,
            'documentos'       => $g->documentos->map(fn($d) => [
                'id'           => $d->id,
                'area_id'      => $d->area_id,
                'area_nombre'  => $d->area?->nombre ?? '',
                'nombre_archivo'=> $d->nombre_archivo,
                'cursos_count' => $d->cursos_count,
            ])->toArray(),
        ];
    }
}
