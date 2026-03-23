<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Imports\HistorialPdfImport;
use App\Models\HistorialAcademico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistorialController extends Controller
{
    /**
     * Devuelve el historial académico del estudiante autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $historial = HistorialAcademico::with('curso')
            ->where('user_id', $user->id)
            ->orderBy('semestre')
            ->orderBy('created_at')
            ->get()
            ->map(fn($h) => [
                'id'       => $h->id,
                'curso_id' => $h->curso_id,
                'codigo'   => $h->curso?->codigo,
                'nombre'   => $h->curso?->nombre,
                'semestre' => $h->semestre,
                'tipo'     => $h->tipo,
                'creditos' => $h->creditos,
                'nota'     => $h->nota,
                'aprobado' => $h->nota > 10 || ($h->fuente === 'autoreporte' && $h->nota === null),
                'fuente'   => $h->fuente,
            ]);

        // Agrupar por semestre para facilitar la presentación en frontend
        $porSemestre = $historial
            ->filter(fn($h) => $h['semestre'] !== null)
            ->groupBy('semestre')
            ->map(fn($cursos, $sem) => [
                'semestre' => $sem,
                'cursos'   => $cursos->values(),
            ])
            ->values();

        // Cursos sin semestre (autoreportados manualmente)
        $sinSemestre = $historial->filter(fn($h) => $h['semestre'] === null)->values();

        return $this->success([
            'por_semestre' => $porSemestre,
            'sin_semestre' => $sinSemestre,
            'total'        => $historial->count(),
            'tiene_historial' => $user->ultima_actualizacion_historial !== null,
            'ultima_actualizacion' => $user->ultima_actualizacion_historial,
        ], 'Historial académico');
    }

    /**
     * Importa el historial desde el PDF del SIGA.
     * Solo el estudiante autenticado puede subir su propio PDF.
     */
    public function importarPdf(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $user = $request->user();

        if (!$user->isEstudiante()) {
            return $this->error('Solo disponible para estudiantes.', 403);
        }

        try {
            $importer = new HistorialPdfImport();
            $importer->import($request->file('archivo')->getPathname(), $user);
            $resumen  = $importer->getResumen();

            return $this->success(
                $resumen,
                "Historial importado: {$resumen['importados']} cursos aprobados registrados."
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Sincroniza (reemplaza) el historial con una lista de curso_ids (autoreporte manual).
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'curso_ids'   => ['present', 'array'],
            'curso_ids.*' => ['uuid', 'exists:cursos,id'],
        ]);

        $user = $request->user();

        HistorialAcademico::where('user_id', $user->id)
            ->where('fuente', 'autoreporte')
            ->delete();

        foreach ($request->curso_ids as $cursoId) {
            HistorialAcademico::updateOrCreate(
                ['user_id' => $user->id, 'curso_id' => $cursoId, 'semestre' => null],
                ['fuente' => 'autoreporte']
            );
        }

        $user->update(['ultima_actualizacion_historial' => now()]);

        return $this->success(
            ['total' => count($request->curso_ids)],
            'Historial académico actualizado correctamente'
        );
    }

    /**
     * Limpia el historial importado del PDF (mantiene autoreportes).
     */
    public function limpiar(Request $request): JsonResponse
    {
        $user = $request->user();

        $eliminados = HistorialAcademico::where('user_id', $user->id)
            ->where('fuente', 'importado')
            ->delete();

        if (!HistorialAcademico::where('user_id', $user->id)->exists()) {
            $user->update(['ultima_actualizacion_historial' => null]);
        }

        return $this->success(
            ['eliminados' => $eliminados],
            'Historial del PDF eliminado correctamente.'
        );
    }
}
