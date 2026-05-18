<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ModificacionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Modificacion\AbrirSeccionRequest;
use App\Http\Requests\Modificacion\CambioAulaRequest;
use App\Http\Requests\Modificacion\CambioAulaYGrupoRequest;
use App\Http\Requests\Modificacion\CambioGrupoRequest;
use App\Http\Requests\Modificacion\CerrarCursoRequest;
use App\Http\Requests\Modificacion\UnificarSeccionesRequest;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use App\Services\ModificacionProgramacionService;
use App\Transformers\ModificacionTransformer;
use Illuminate\Http\JsonResponse;
use Exception;

class ProgramacionModificacionController extends Controller
{
    public function __construct(
        protected ModificacionProgramacionService  $service,
        protected ModificacionTransformer          $transformer,
        protected ProgramacionRepositoryInterface  $progRepository
    ) {}

    /**
     * PATCH /programacion/{id}/cerrar
     */
    public function cerrar(CerrarCursoRequest $request, string $id): JsonResponse
    {
        $prog = $this->progRepository->findById($id);

        if (!$prog) {
            return $this->notFound('Sección no encontrada.');
        }

        try {
            $modificacion = $this->service->cerrarCurso(
                $prog,
                $request->user()->id,
                $request->validated('motivo')
            );

            return $this->success(
                $this->transformer->toArray($modificacion->load(['periodo', 'user', 'programacion.curso.area', 'programacion.aula', 'programacion.grupoHorario'])),
                'Curso cerrado y modificación registrada.'
            );
        } catch (ModificacionException $e) {
            return $this->error($e->getMessage());
        } catch (Exception $e) {
            report($e);
            return $this->error('Error interno al procesar la modificación.', 500);
        }
    }

    /**
     * POST /programacion/abrir-seccion
     */
    public function abrirSeccion(AbrirSeccionRequest $request): JsonResponse
    {
        try {
            $modificacion = $this->service->abrirSeccion(
                $request->validated(),
                $request->user()->id
            );

            return $this->created(
                $this->transformer->toArray($modificacion->load(['periodo', 'user', 'programacion.curso.area', 'programacion.aula', 'programacion.grupoHorario'])),
                'Sección abierta y modificación registrada.'
            );
        } catch (ModificacionException $e) {
            return $this->error($e->getMessage());
        } catch (Exception $e) {
            report($e);
            return $this->error('Error interno al procesar la modificación.', 500);
        }
    }

    /**
     * PATCH /programacion/{id}/aula
     */
    public function cambiarAula(CambioAulaRequest $request, string $id): JsonResponse
    {
        $prog = $this->progRepository->findById($id);

        if (!$prog) {
            return $this->notFound('Sección no encontrada.');
        }

        try {
            $modificacion = $this->service->cambiarAula(
                $prog,
                $request->validated('aula_id'),
                $request->user()->id,
                $request->validated('motivo')
            );

            return $this->success(
                $this->transformer->toArray($modificacion->load(['periodo', 'user', 'programacion.curso.area', 'programacion.aula', 'programacion.grupoHorario'])),
                'Aula actualizada y modificación registrada.'
            );
        } catch (ModificacionException $e) {
            return $this->error($e->getMessage());
        } catch (Exception $e) {
            report($e);
            return $this->error('Error interno al procesar la modificación.', 500);
        }
    }

    /**
     * PATCH /programacion/{id}/grupo
     */
    public function cambiarGrupo(CambioGrupoRequest $request, string $id): JsonResponse
    {
        $prog = $this->progRepository->findById($id);

        if (!$prog) {
            return $this->notFound('Sección no encontrada.');
        }

        try {
            $modificacion = $this->service->cambiarGrupo(
                $prog,
                $request->validated('grupo_horario_id'),
                $request->user()->id,
                $request->validated('motivo')
            );

            return $this->success(
                $this->transformer->toArray($modificacion->load(['periodo', 'user', 'programacion.curso.area', 'programacion.aula', 'programacion.grupoHorario'])),
                'Grupo horario actualizado y modificación registrada.'
            );
        } catch (ModificacionException $e) {
            return $this->error($e->getMessage());
        } catch (Exception $e) {
            report($e);
            return $this->error('Error interno al procesar la modificación.', 500);
        }
    }

    /**
     * PATCH /programacion/{id}/aula-grupo
     * Uso exclusivo desde la vista matriz (drag a celda diferente en ambos ejes).
     */
    public function cambiarAulaYGrupo(CambioAulaYGrupoRequest $request, string $id): JsonResponse
    {
        $prog = $this->progRepository->findById($id);

        if (!$prog) {
            return $this->notFound('Sección no encontrada.');
        }

        try {
            $modificacion = $this->service->cambiarAulaYGrupo(
                $prog,
                $request->validated('aula_id'),
                $request->validated('grupo_horario_id'),
                $request->user()->id,
                $request->validated('motivo')
            );

            return $this->success(
                $this->transformer->toArray($modificacion->load(['periodo', 'user', 'programacion.curso.area', 'programacion.aula', 'programacion.grupoHorario'])),
                'Aula y grupo actualizados y modificación registrada.'
            );
        } catch (ModificacionException $e) {
            return $this->error($e->getMessage());
        } catch (Exception $e) {
            report($e);
            return $this->error('Error interno al procesar la modificación.', 500);
        }
    }

    /**
     * POST /programacion/unificar
     */
    public function unificar(UnificarSeccionesRequest $request): JsonResponse
    {
        try {
            $modificacion = $this->service->unificarSecciones(
                $request->validated('programacion_destino_id'),
                $request->validated('secciones_origen_ids'),
                $request->user()->id,
                $request->validated('motivo')
            );

            return $this->created(
                $this->transformer->toArray($modificacion->load(['periodo', 'user', 'programacion.curso.area', 'programacion.aula', 'programacion.grupoHorario'])),
                'Secciones unificadas y modificación registrada.'
            );
        } catch (ModificacionException $e) {
            return $this->error($e->getMessage());
        } catch (Exception $e) {
            report($e);
            return $this->error($e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }
}
