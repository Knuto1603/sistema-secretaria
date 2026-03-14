<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ProgresoAcademicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgresoController extends Controller
{
    public function __construct(
        protected ProgresoAcademicoService $service
    ) {}

    /**
     * Progreso académico del estudiante autenticado
     * GET /progreso/mi-progreso
     */
    public function miProgreso(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isEstudiante()) {
            return $this->error('Solo disponible para estudiantes.', 403);
        }

        $progreso = $this->service->calcularProgreso($user);

        return $this->success($progreso, 'Progreso académico');
    }

    /**
     * Progreso académico de un alumno específico (admin)
     * GET /progreso/{userId}
     */
    public function progresoAlumno(string $userId): JsonResponse
    {
        $alumno = User::find($userId);

        if (!$alumno || !$alumno->isEstudiante()) {
            return $this->notFound('Estudiante no encontrado');
        }

        $progreso = $this->service->calcularProgreso($alumno);

        return $this->success(array_merge($progreso, [
            'estudiante' => [
                'id'                   => $alumno->id,
                'name'                 => $alumno->name,
                'codigo_universitario' => $alumno->codigo_universitario,
                'egresante'            => $alumno->egresante,
            ],
        ]), 'Progreso académico del alumno');
    }

    /**
     * Marcar/desmarcar manualmente como egresante (admin/secretaria)
     * PATCH /progreso/{userId}/egresante
     */
    public function toggleEgresante(string $userId): JsonResponse
    {
        $alumno = User::find($userId);

        if (!$alumno || !$alumno->isEstudiante()) {
            return $this->notFound('Estudiante no encontrado');
        }

        $alumno->update(['egresante' => !$alumno->egresante]);

        return $this->success([
            'egresante' => $alumno->fresh()->egresante,
        ], $alumno->egresante ? 'Marcado como egresante' : 'Egresante removido');
    }
}
