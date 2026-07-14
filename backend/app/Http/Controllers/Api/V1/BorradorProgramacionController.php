<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BorradorProgramacion;
use App\Services\BorradorProgramacionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BorradorProgramacionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly BorradorProgramacionService $service
    ) {}

    /**
     * Lista borradores de un período.
     * GET /programacion-interactiva?periodo_id=xxx
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['periodo_id' => 'required|uuid|exists:periodos,id']);

        $borradores = $this->service->listar($request->periodo_id);

        return $this->success($borradores->map(fn($b) => [
            'id'          => $b->id,
            'nombre'      => $b->nombre,
            'ciclo_tipo'  => $b->ciclo_tipo,
            'estado'      => $b->estado,
            'total_secciones' => $b->secciones()->count(),
            'periodo'     => ['id' => $b->periodo->id, 'nombre' => $b->periodo->nombre],
            'creado_por'  => $b->creadoPor?->name,
            'created_at'  => $b->created_at,
        ]));
    }

    /**
     * Detalle de un borrador con todas sus secciones.
     * GET /programacion-interactiva/{id}
     */
    public function show(string $id): JsonResponse
    {
        $borrador = $this->service->obtener($id);
        return $this->success($this->transformBorrador($borrador));
    }

    /**
     * Genera un borrador desde el plan de estudios activo.
     * POST /programacion-interactiva/generar
     */
    public function generar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'periodo_id' => 'required|uuid|exists:periodos,id',
            'ciclo_tipo' => 'required|in:par,impar',
            'nombre'     => 'required|string|max:50',
        ]);

        $borrador = $this->service->generar(
            $data['periodo_id'],
            $data['ciclo_tipo'],
            $data['nombre'],
            $request->user()
        );

        return $this->created($this->transformBorrador($borrador), 'Borrador generado correctamente.');
    }

    /**
     * Crea un borrador importando la Matriz de Programación Académica.
     * POST /programacion-interactiva/importar-matriz
     */
    public function importarMatriz(Request $request): JsonResponse
    {
        $request->validate([
            'periodo_id' => 'required|uuid|exists:periodos,id',
            'ciclo_tipo' => 'required|in:par,impar',
            'nombre'     => 'required|string|max:50',
            'file'       => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $resultado = $this->service->importarMatriz(
                $request->periodo_id,
                $request->ciclo_tipo,
                $request->nombre,
                $request->user(),
                $request->file('file')
            );

            $resumen  = $resultado['resumen'];
            $borrador = $resultado['borrador'];
            $msg = "Borrador creado: {$resumen['importados']} secciones importadas, {$resumen['omitidos']} omitidas.";

            return $this->created([
                'borrador' => $this->transformBorrador($borrador),
                'resumen'  => $resumen,
            ], $msg);
        } catch (\Exception $e) {
            return $this->error('Error al importar la matriz: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Agrega una sección manual al borrador.
     * POST /programacion-interactiva/{id}/secciones
     */
    public function agregarSeccion(Request $request, string $id): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);

        $data = $request->validate([
            'curso_id'         => 'required|uuid|exists:cursos,id',
            'escuela_id'       => 'required|uuid|exists:escuelas,id',
            'ciclo'            => 'required|integer|min:1|max:10',
            'tipo'             => 'required|in:O,E',
            'docente_id'       => 'nullable|uuid|exists:docentes,id',
            'aula_id'          => 'nullable|uuid|exists:aulas,id',
            'grupo_horario_id' => 'nullable|uuid|exists:grupos_horario,id',
            'capacidad'        => 'nullable|integer|min:1|max:500',
        ]);

        $seccion = $this->service->agregarSeccion($borrador, $data);

        return $this->created($this->transformSeccion($seccion), 'Sección agregada.');
    }

    /**
     * Actualiza una sección (aula, horario, docente, capacidad).
     * PUT /programacion-interactiva/{id}/secciones/{seccionId}
     */
    public function updateSeccion(Request $request, string $id, string $seccionId): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);

        $data = $request->validate([
            'docente_id'       => 'sometimes|nullable|uuid|exists:docentes,id',
            'aula_id'          => 'sometimes|nullable|uuid|exists:aulas,id',
            'grupo_horario_id' => 'sometimes|nullable|uuid|exists:grupos_horario,id',
            'capacidad'        => 'sometimes|integer|min:1|max:500',
        ]);

        $seccion = $this->service->actualizarSeccion($borrador, $seccionId, $data);

        return $this->success($this->transformSeccion($seccion), 'Sección actualizada.');
    }

    /**
     * Actualización masiva para drag & drop.
     * PATCH /programacion-interactiva/{id}/secciones/bulk
     */
    public function bulkUpdate(Request $request, string $id): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);

        $data = $request->validate([
            'cambios'                        => 'required|array|min:1',
            'cambios.*.id'                   => 'required|uuid',
            'cambios.*.aula_id'              => 'nullable|uuid|exists:aulas,id',
            'cambios.*.grupo_horario_id'     => 'nullable|uuid|exists:grupos_horario,id',
        ]);

        $this->service->bulkActualizar($borrador, $data['cambios']);

        return $this->success(null, 'Secciones actualizadas.');
    }

    /**
     * Elimina una sección del borrador.
     * DELETE /programacion-interactiva/{id}/secciones/{seccionId}
     */
    public function deleteSeccion(string $id, string $seccionId): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);
        $this->service->eliminarSeccion($borrador, $seccionId);

        return $this->success(null, 'Sección eliminada.');
    }

    /**
     * Publica el borrador → crea registros en programacion_academica.
     * POST /programacion-interactiva/{id}/publicar
     */
    public function publicar(Request $request, string $id): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);

        try {
            $borrador = $this->service->publicar($borrador, $request->user());
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(
            ['estado' => $borrador->estado, 'publicado_at' => $borrador->publicado_at],
            'Borrador publicado. La programación académica ha sido generada.'
        );
    }

    /**
     * Auto-asigna aulas y grupos a todas las secciones del borrador.
     * POST /programacion-interactiva/{id}/auto-asignar
     */
    public function autoAsignar(string $id): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);
        $resultado = $this->service->autoAsignar($borrador);

        $msg = "Auto-asignación completada: {$resultado['asignadas']} de {$resultado['total']} secciones asignadas.";
        if ($resultado['sin_asignar'] > 0) {
            $msg .= " {$resultado['sin_asignar']} no pudieron asignarse por falta de espacio.";
        }

        return $this->success($resultado, $msg);
    }

    /**
     * Revierte un borrador publicado a estado 'borrador',
     * eliminando los registros de programacion_academica que generó.
     * POST /programacion-interactiva/{id}/revertir
     */
    public function revertir(Request $request, string $id): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);
        $borrador = $this->service->revertir($borrador);

        return $this->success(
            ['estado' => $borrador->estado],
            'Borrador revertido a estado borrador. La programación académica generada fue eliminada.'
        );
    }

    /**
     * Elimina un borrador completo (funciona para ambos estados).
     * DELETE /programacion-interactiva/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $borrador = BorradorProgramacion::findOrFail($id);
        $this->service->eliminar($borrador);

        return $this->success(null, 'Borrador eliminado.');
    }

    // ─── Transformers ────────────────────────────────────────────────────────

    private function transformBorrador(BorradorProgramacion $b): array
    {
        return [
            'id'          => $b->id,
            'nombre'      => $b->nombre,
            'ciclo_tipo'  => $b->ciclo_tipo,
            'estado'      => $b->estado,
            'periodo'     => $b->periodo ? ['id' => $b->periodo->id, 'nombre' => $b->periodo->nombre] : null,
            'creado_por'  => $b->creadoPor?->name,
            'publicado_por' => $b->publicadoPor?->name,
            'publicado_at'  => $b->publicado_at,
            'created_at'  => $b->created_at,
            'secciones'   => $b->relationLoaded('secciones')
                ? $b->secciones->map(fn($s) => $this->transformSeccion($s))->values()
                : [],
        ];
    }

    private function transformSeccion(\App\Models\BorradorSeccion $s): array
    {
        return [
            'id'               => $s->id,
            'borrador_id'      => $s->programacion_id,
            'curso'            => $s->relationLoaded('curso') ? [
                'id'     => $s->curso->id,
                'codigo' => $s->curso->codigo,
                'nombre' => $s->curso->nombre,
            ] : ['id' => $s->curso_id],
            'escuela'          => ($s->relationLoaded('escuela') && $s->escuela) ? [
                'id'          => $s->escuela->id,
                'nombre'      => $s->escuela->nombre,
                'nombre_corto' => $s->escuela->nombre_corto,
            ] : ['id' => $s->escuela_programada_id],
            'ciclo'            => $s->ciclo,
            'tipo'             => $s->tipo,
            'seccion'          => $s->seccion,
            'capacidad'        => $s->capacidad,
            'esta_asignado'    => $s->estaAsignado(),
            'docente'          => $s->relationLoaded('docente') && $s->docente
                ? ['id' => $s->docente->id, 'nombre_completo' => $s->docente->nombre_completo]
                : null,
            'aula'             => ($s->relationLoaded('aulaRelacion') && $s->aulaRelacion) ? [
                'id'       => $s->aulaRelacion->id,
                'nombre'   => $s->aulaRelacion->nombre,
                'capacidad' => $s->aulaRelacion->capacidad,
                'pabellon' => ($s->aulaRelacion->relationLoaded('pabellon') && $s->aulaRelacion->pabellon)
                    ? ['id' => $s->aulaRelacion->pabellon->id, 'nombre' => $s->aulaRelacion->pabellon->nombre]
                    : null,
            ] : null,
            'grupo_horario'    => $s->relationLoaded('grupoHorario') && $s->grupoHorario ? [
                'id'       => $s->grupoHorario->id,
                'nombre'   => $s->grupoHorario->nombre,
                'detalles' => $s->grupoHorario->relationLoaded('detalles')
                    ? $s->grupoHorario->detalles->map(fn($d) => [
                        'dia_semana'  => $d->dia_semana,
                        'hora_inicio' => $d->hora_inicio,
                        'hora_fin'    => $d->hora_fin,
                    ])->values()
                    : [],
            ] : null,
        ];
    }
}
