<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            ->get()
            ->map(fn($h) => [
                'id'       => $h->id,
                'curso_id' => $h->curso_id,
                'codigo'   => $h->curso->codigo,
                'nombre'   => $h->curso->nombre,
                'fuente'   => $h->fuente,
            ]);

        return $this->success([
            'cursos' => $historial,
            'total'  => $historial->count(),
        ], 'Historial académico');
    }

    /**
     * Sincroniza (reemplaza) el historial del estudiante con la lista de curso_ids recibida.
     * Acepta un array vacío para indicar que no ha aprobado aún ningún curso.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'curso_ids'   => ['present', 'array'],
            'curso_ids.*' => ['uuid', 'exists:cursos,id'],
        ]);

        $user = $request->user();

        HistorialAcademico::where('user_id', $user->id)->delete();

        foreach ($request->curso_ids as $cursoId) {
            HistorialAcademico::create([
                'user_id'  => $user->id,
                'curso_id' => $cursoId,
                'fuente'   => 'autoreporte',
            ]);
        }

        $user->update(['ultima_actualizacion_historial' => now()]);

        return $this->success(
            ['total' => count($request->curso_ids)],
            'Historial académico actualizado correctamente'
        );
    }
}
