<?php

namespace App\Services;

use App\Exceptions\ModificacionException;
use App\Models\Aula;
use App\Models\BorradorProgramacion;
use App\Models\GrupoHorario;
use App\Models\ModificacionProgramacion;
use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\ModificacionRepositoryInterface;
use App\Repositories\Contracts\ProgramacionRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ModificacionProgramacionService
{
    public function __construct(
        protected ModificacionRepositoryInterface  $repository,
        protected ProgramacionRepositoryInterface  $progRepository
    ) {}

    /**
     * Cierra un curso marcándolo como lleno manualmente.
     */
    public function cerrarCurso(ProgramacionAcademica $prog, string $userId, string $motivo): ModificacionProgramacion
    {
        if ($prog->lleno_manual) {
            throw new ModificacionException('El curso ya está cerrado manualmente.');
        }

        return DB::transaction(function () use ($prog, $userId, $motivo) {
            $anterior = $this->snapshotBase($prog);

            $prog->update(['lleno_manual' => true]);

            return $this->repository->create([
                'periodo_id'       => $prog->periodo_id,
                'borrador_id'      => $this->resolverBorradorId($prog->periodo_id),
                'tipo'             => 'cerrar_curso',
                'programacion_id'  => $prog->id,
                'datos_anteriores' => $anterior,
                'datos_nuevos'     => array_merge($anterior, ['lleno_manual' => true]),
                'motivo'           => $motivo,
                'user_id'          => $userId,
            ]);
        });
    }

    /**
     * Abre una nueva sección de un curso en un periodo.
     */
    public function abrirSeccion(array $data, string $userId): ModificacionProgramacion
    {
        return DB::transaction(function () use ($data, $userId) {
            $nuevaProg = $this->progRepository->create([
                'curso_id'             => $data['curso_id'],
                'periodo_id'           => $data['periodo_id'],
                'capacidad'            => $data['capacidad'],
                'grupo'                => $data['grupo'],
                'ciclo'                => $data['ciclo'] ?? null,
                'aula_id'              => $data['aula_id'] ?? null,
                'grupo_horario_id'     => $data['grupo_horario_id'] ?? null,
                'docente_id'           => $data['docente_id'] ?? null,
                'escuela_programada_id'=> $data['escuela_programada_id'] ?? null,
                'n_inscritos'          => 0,
                'lleno_manual'         => false,
            ]);

            return $this->repository->create([
                'periodo_id'       => $data['periodo_id'],
                'borrador_id'      => $this->resolverBorradorId($data['periodo_id']),
                'tipo'             => 'abrir_seccion',
                'programacion_id'  => $nuevaProg->id,
                'datos_anteriores' => [],
                'datos_nuevos'     => $this->snapshotBase($nuevaProg),
                'motivo'           => $data['motivo'],
                'user_id'          => $userId,
            ]);
        });
    }

    /**
     * Cambia el aula de una sección.
     */
    public function cambiarAula(ProgramacionAcademica $prog, string $nuevaAulaId, string $userId, string $motivo): ModificacionProgramacion
    {
        if ($prog->aula_id === $nuevaAulaId) {
            throw new ModificacionException('La sección ya está asignada a esa aula.');
        }

        return DB::transaction(function () use ($prog, $nuevaAulaId, $userId, $motivo) {
            $anterior  = $this->snapshotAula($prog);
            $nuevaAula = Aula::findOrFail($nuevaAulaId);

            $prog->update(['aula_id' => $nuevaAulaId]);

            return $this->repository->create([
                'periodo_id'       => $prog->periodo_id,
                'borrador_id'      => $this->resolverBorradorId($prog->periodo_id),
                'tipo'             => 'cambio_aula',
                'programacion_id'  => $prog->id,
                'datos_anteriores' => $anterior,
                'datos_nuevos'     => ['aula_id' => $nuevaAulaId, 'aula_nombre' => $nuevaAula->nombre],
                'motivo'           => $motivo,
                'user_id'          => $userId,
            ]);
        });
    }

    /**
     * Cambia el grupo horario de una sección.
     */
    public function cambiarGrupo(ProgramacionAcademica $prog, string $nuevoGrupoId, string $userId, string $motivo): ModificacionProgramacion
    {
        if ($prog->grupo_horario_id === $nuevoGrupoId) {
            throw new ModificacionException('La sección ya está asignada a ese grupo horario.');
        }

        return DB::transaction(function () use ($prog, $nuevoGrupoId, $userId, $motivo) {
            $anterior   = $this->snapshotGrupo($prog);
            $nuevoGrupo = GrupoHorario::findOrFail($nuevoGrupoId);

            $prog->update(['grupo_horario_id' => $nuevoGrupoId]);

            return $this->repository->create([
                'periodo_id'       => $prog->periodo_id,
                'borrador_id'      => $this->resolverBorradorId($prog->periodo_id),
                'tipo'             => 'cambio_grupo',
                'programacion_id'  => $prog->id,
                'datos_anteriores' => $anterior,
                'datos_nuevos'     => ['grupo_horario_id' => $nuevoGrupoId, 'grupo_horario_nombre' => $nuevoGrupo->nombre],
                'motivo'           => $motivo,
                'user_id'          => $userId,
            ]);
        });
    }

    /**
     * Cambia aula y grupo simultáneamente (originado desde la vista matriz).
     */
    public function cambiarAulaYGrupo(ProgramacionAcademica $prog, string $nuevaAulaId, string $nuevoGrupoId, string $userId, string $motivo): ModificacionProgramacion
    {
        if ($prog->aula_id === $nuevaAulaId && $prog->grupo_horario_id === $nuevoGrupoId) {
            throw new ModificacionException('La sección ya está asignada a esa aula y grupo horario.');
        }

        return DB::transaction(function () use ($prog, $nuevaAulaId, $nuevoGrupoId, $userId, $motivo) {
            $anterior   = array_merge($this->snapshotAula($prog), $this->snapshotGrupo($prog));
            $nuevaAula  = Aula::findOrFail($nuevaAulaId);
            $nuevoGrupo = GrupoHorario::findOrFail($nuevoGrupoId);

            $prog->update(['aula_id' => $nuevaAulaId, 'grupo_horario_id' => $nuevoGrupoId]);

            return $this->repository->create([
                'periodo_id'       => $prog->periodo_id,
                'borrador_id'      => $this->resolverBorradorId($prog->periodo_id),
                'tipo'             => 'cambio_aula_y_grupo',
                'programacion_id'  => $prog->id,
                'datos_anteriores' => $anterior,
                'datos_nuevos'     => [
                    'aula_id'             => $nuevaAulaId,
                    'aula_nombre'         => $nuevaAula->nombre,
                    'grupo_horario_id'    => $nuevoGrupoId,
                    'grupo_horario_nombre'=> $nuevoGrupo->nombre,
                ],
                'motivo'  => $motivo,
                'user_id' => $userId,
            ]);
        });
    }

    /**
     * Unifica varias secciones en una sección destino.
     * Las secciones origen quedan cerradas manualmente (absorbidas).
     */
    public function unificarSecciones(string $destinoId, array $origenIds, string $userId, string $motivo): ModificacionProgramacion
    {
        return DB::transaction(function () use ($destinoId, $origenIds, $userId, $motivo) {
            $destino  = ProgramacionAcademica::with(['aulaRelacion:id,nombre', 'grupoHorario:id,nombre'])->findOrFail($destinoId);
            $origenes = ProgramacionAcademica::with(['aulaRelacion:id,nombre', 'grupoHorario:id,nombre'])
                ->whereIn('id', $origenIds)
                ->get();

            if ($origenes->count() !== count($origenIds)) {
                throw new ModificacionException('Una o más secciones origen no fueron encontradas.');
            }

            $cursoIds   = $origenes->pluck('curso_id')->push($destino->curso_id)->unique();
            $periodoIds = $origenes->pluck('periodo_id')->push($destino->periodo_id)->unique();

            if ($cursoIds->count() > 1) {
                throw new ModificacionException('Todas las secciones deben pertenecer al mismo curso.');
            }

            if ($periodoIds->count() > 1) {
                throw new ModificacionException('Todas las secciones deben pertenecer al mismo periodo.');
            }

            $this->progRepository->cerrarMasivo($origenIds);

            return $this->repository->create([
                'periodo_id'          => $destino->periodo_id,
                'borrador_id'         => $this->resolverBorradorId($destino->periodo_id),
                'tipo'                => 'unificacion_secciones',
                'programacion_id'     => $destinoId,
                'secciones_origen_ids'=> $origenIds,
                'datos_anteriores'    => [
                    'secciones_origen' => $origenes->map(fn ($s) => $this->snapshotBase($s))->values()->all(),
                ],
                'datos_nuevos'        => $this->snapshotBase($destino),
                'motivo'              => $motivo,
                'user_id'             => $userId,
            ]);
        });
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function resolverBorradorId(string $periodoId): ?string
    {
        return BorradorProgramacion::where('periodo_id', $periodoId)
            ->where('estado', 'publicado')
            ->value('id');
    }

    // ─── Helpers de snapshot ────────────────────────────────────────────────

    private function snapshotBase(ProgramacionAcademica $prog): array
    {
        $aulaNombre = $prog->relationLoaded('aulaRelacion')
            ? $prog->aulaRelacion?->nombre
            : ($prog->aula_id ? Aula::find($prog->aula_id)?->nombre : null);

        $grupoNombre = $prog->relationLoaded('grupoHorario')
            ? $prog->grupoHorario?->nombre
            : ($prog->grupo_horario_id ? GrupoHorario::find($prog->grupo_horario_id)?->nombre : null);

        return [
            'grupo'                => $prog->grupo,
            'seccion'              => $prog->seccion,
            'ciclo'                => $prog->ciclo,
            'capacidad'            => $prog->capacidad,
            'n_inscritos'          => $prog->n_inscritos,
            'lleno_manual'         => $prog->lleno_manual,
            'aula_id'              => $prog->aula_id,
            'aula_nombre'          => $aulaNombre,
            'grupo_horario_id'     => $prog->grupo_horario_id,
            'grupo_horario_nombre' => $grupoNombre,
        ];
    }

    private function snapshotAula(ProgramacionAcademica $prog): array
    {
        $nombreAula = null;
        if ($prog->aula_id) {
            $nombreAula = $prog->relationLoaded('aulaRelacion')
                ? $prog->aulaRelacion?->nombre
                : Aula::find($prog->aula_id)?->nombre;
        }

        return ['aula_id' => $prog->aula_id, 'aula_nombre' => $nombreAula];
    }

    private function snapshotGrupo(ProgramacionAcademica $prog): array
    {
        $nombreGrupo = null;
        if ($prog->grupo_horario_id) {
            $nombreGrupo = $prog->relationLoaded('grupoHorario')
                ? $prog->grupoHorario?->nombre
                : GrupoHorario::find($prog->grupo_horario_id)?->nombre;
        }

        return ['grupo_horario_id' => $prog->grupo_horario_id, 'grupo_horario_nombre' => $nombreGrupo];
    }
}
