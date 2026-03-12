<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Imports\InscripcionesHtmlImport;
use App\Models\Inscripcion;
use App\Models\ProgramacionAcademica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscripcionController extends Controller
{
    /**
     * POST /programacion/inscripciones/import-html
     * Importa inscripciones desde el reporte "ALUMNOS POR CURSO" del SIGA.
     */
    public function importHtml(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:htm,html', 'max:10240'],
        ]);

        try {
            $importer = new InscripcionesHtmlImport();
            $importer->import($request->file('file')->getPathname());

            return $this->success(
                $importer->getResumen(),
                'Inscripciones importadas desde reporte SIGA'
            );
        } catch (\Exception $e) {
            return $this->error('Error al procesar el archivo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /programacion/{id}/inscripciones
     * Lista paginada de alumnos inscritos en una sección.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $programacion = ProgramacionAcademica::find($id);

        if (!$programacion) {
            return $this->notFound('Programación no encontrada');
        }

        $perPage = (int) $request->get('per_page', 20);

        $inscripciones = Inscripcion::where('programacion_id', $id)
            ->with(['estudiante.escuela'])
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $items = $inscripciones->getCollection()->map(fn($i) => [
            'id'        => $i->id,
            'fuente'    => $i->fuente,
            'estudiante' => $i->estudiante ? [
                'id'                  => $i->estudiante->id,
                'codigo_universitario'=> $i->estudiante->codigo_universitario,
                'nombre'              => $i->estudiante->name,
                'escuela'             => $i->estudiante->escuela ? [
                    'id'          => $i->estudiante->escuela->id,
                    'nombre'      => $i->estudiante->escuela->nombre,
                    'nombre_corto'=> $i->estudiante->escuela->nombre_corto,
                ] : null,
            ] : null,
            'created_at' => $i->created_at->toISOString(),
        ])->toArray();

        return $this->paginated($items, $inscripciones, 'Alumnos inscritos');
    }

    /**
     * GET /programacion/{id}/inscripciones/stats
     * Conteo de inscritos por escuela (para gráfico de torta).
     */
    public function stats(string $id): JsonResponse
    {
        $programacion = ProgramacionAcademica::find($id);

        if (!$programacion) {
            return $this->notFound('Programación no encontrada');
        }

        $total = Inscripcion::where('programacion_id', $id)->count();

        $porEscuela = Inscripcion::where('inscripciones.programacion_id', $id)
            ->join('users', 'inscripciones.user_id', '=', 'users.id')
            ->leftJoin('escuelas', 'users.escuela_id', '=', 'escuelas.id')
            ->selectRaw('escuelas.id as escuela_id, escuelas.nombre, escuelas.nombre_corto, COUNT(*) as cantidad')
            ->groupBy('escuelas.id', 'escuelas.nombre', 'escuelas.nombre_corto')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn($row) => [
                'escuela_id'   => $row->escuela_id,
                'nombre'       => $row->nombre ?? 'Sin escuela',
                'nombre_corto' => $row->nombre_corto ?? '—',
                'cantidad'     => (int) $row->cantidad,
                'porcentaje'   => $total > 0 ? round(($row->cantidad / $total) * 100, 1) : 0,
            ]);

        return $this->success([
            'total'       => $total,
            'por_escuela' => $porEscuela,
        ], 'Estadísticas de inscripción');
    }

    /**
     * DELETE /programacion/{id}/inscripciones/{userId}
     * Elimina manualmente una inscripción y recalcula n_inscritos.
     */
    public function destroy(string $id, string $userId): JsonResponse
    {
        $inscripcion = Inscripcion::where('programacion_id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$inscripcion) {
            return $this->notFound('Inscripción no encontrada');
        }

        $inscripcion->delete();

        // Recalcular n_inscritos
        $count = Inscripcion::where('programacion_id', $id)->count();
        ProgramacionAcademica::where('id', $id)->update(['n_inscritos' => $count]);

        return $this->success(null, 'Inscripción eliminada correctamente');
    }
}
