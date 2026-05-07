<?php

namespace App\Services;

use App\Models\BorradorProgramacion;
use App\Models\BorradorSeccion;
use App\Models\Escuela;
use App\Models\Plan;
use App\Models\ProgramacionAcademica;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class BorradorProgramacionService
{
    /**
     * Lista borradores de un período, ordenados por fecha de creación desc.
     */
    public function listar(string $periodoId): Collection
    {
        return BorradorProgramacion::with(['periodo', 'creadoPor'])
            ->where('periodo_id', $periodoId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Obtiene un borrador con todas sus secciones y sus relaciones.
     */
    public function obtener(string $id): BorradorProgramacion
    {
        return BorradorProgramacion::with([
            'periodo',
            'creadoPor',
            'publicadoPor',
            'secciones.curso',
            'secciones.escuela',
            'secciones.docente',
            'secciones.aula.pabellon',
            'secciones.grupoHorario.detalles',
        ])->findOrFail($id);
    }

    /**
     * Genera un nuevo borrador con una sección 'A' por cada curso obligatorio
     * del plan activo de cada escuela, según el tipo de ciclo (par/impar).
     */
    public function generar(
        string $periodoId,
        string $cicloTipo,
        string $nombre,
        User $creadoPor
    ): BorradorProgramacion {
        $ciclos = $cicloTipo === 'par'
            ? [2, 4, 6, 8, 10]
            : [1, 3, 5, 7, 9];

        return DB::transaction(function () use ($periodoId, $cicloTipo, $nombre, $creadoPor, $ciclos) {
            $borrador = BorradorProgramacion::create([
                'periodo_id' => $periodoId,
                'nombre'     => $nombre,
                'ciclo_tipo' => $cicloTipo,
                'estado'     => 'borrador',
                'creado_por' => $creadoPor->id,
            ]);

            $escuelas = Escuela::all();
            $secciones = [];

            foreach ($escuelas as $escuela) {
                $plan = Plan::where('escuela_id', $escuela->id)
                    ->where('activo', true)
                    ->first();

                if (!$plan) {
                    continue;
                }

                $cursosDePlan = $plan->cursos()
                    ->with('curso')
                    ->where('tipo', 'O')
                    ->whereIn('ciclo', $ciclos)
                    ->orderBy('ciclo')
                    ->get();

                foreach ($cursosDePlan as $cursoPlan) {
                    $secciones[] = [
                        'id'               => (string) \Illuminate\Support\Str::uuid(),
                        'borrador_id'      => $borrador->id,
                        'curso_id'         => $cursoPlan->curso_id,
                        'escuela_id'       => $escuela->id,
                        'ciclo'            => $cursoPlan->ciclo,
                        'tipo'             => 'O',
                        'seccion'          => 'A',
                        'docente_id'       => null,
                        'aula_id'          => null,
                        'grupo_horario_id' => null,
                        'capacidad'        => 30,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }
            }

            if (!empty($secciones)) {
                BorradorSeccion::insert($secciones);
            }

            return $this->obtener($borrador->id);
        });
    }

    /**
     * Agrega una sección manual (obligatorio extra o electivo) al borrador.
     */
    public function agregarSeccion(BorradorProgramacion $borrador, array $data): BorradorSeccion
    {
        $this->verificarEditable($borrador);

        // Calcular la siguiente letra de sección para este curso en esta escuela
        $ultimaSeccion = BorradorSeccion::where('borrador_id', $borrador->id)
            ->where('curso_id', $data['curso_id'])
            ->where('escuela_id', $data['escuela_id'])
            ->orderByDesc('seccion')
            ->value('seccion');

        $data['seccion'] = $this->siguienteSeccion($ultimaSeccion);
        $data['borrador_id'] = $borrador->id;

        $seccion = BorradorSeccion::create($data);

        return $seccion->load(['curso', 'escuela', 'docente', 'aula.pabellon', 'grupoHorario.detalles']);
    }

    /**
     * Actualiza los datos de asignación de una sección (aula, horario, docente, capacidad).
     */
    public function actualizarSeccion(BorradorProgramacion $borrador, string $seccionId, array $data): BorradorSeccion
    {
        $this->verificarEditable($borrador);

        $seccion = BorradorSeccion::where('borrador_id', $borrador->id)
            ->findOrFail($seccionId);

        $campos = ['aula_id', 'grupo_horario_id', 'docente_id', 'capacidad'];
        $seccion->update(array_intersect_key($data, array_flip($campos)));

        return $seccion->load(['curso', 'escuela', 'docente', 'aula.pabellon', 'grupoHorario.detalles']);
    }

    /**
     * Actualización masiva de secciones (para drag & drop).
     * Recibe array de [{ id, aula_id, grupo_horario_id }].
     */
    public function bulkActualizar(BorradorProgramacion $borrador, array $cambios): void
    {
        $this->verificarEditable($borrador);

        DB::transaction(function () use ($borrador, $cambios) {
            foreach ($cambios as $cambio) {
                BorradorSeccion::where('borrador_id', $borrador->id)
                    ->where('id', $cambio['id'])
                    ->update([
                        'aula_id'          => $cambio['aula_id'] ?? null,
                        'grupo_horario_id' => $cambio['grupo_horario_id'] ?? null,
                        'updated_at'       => now(),
                    ]);
            }
        });
    }

    /**
     * Elimina una sección del borrador.
     */
    public function eliminarSeccion(BorradorProgramacion $borrador, string $seccionId): void
    {
        $this->verificarEditable($borrador);

        BorradorSeccion::where('borrador_id', $borrador->id)
            ->findOrFail($seccionId)
            ->delete();
    }

    /**
     * Publica el borrador: crea registros en programacion_academica.
     * Si ya existe una sección del mismo curso+escuela+seccion en el período, la omite.
     */
    public function publicar(BorradorProgramacion $borrador, User $publicadoPor): BorradorProgramacion
    {
        $this->verificarEditable($borrador);

        return DB::transaction(function () use ($borrador, $publicadoPor) {
            $secciones = $borrador->secciones()->with('curso')->get();

            foreach ($secciones as $seccion) {
                // Evitar duplicados por curso + escuela_programada + seccion en el período
                $existe = ProgramacionAcademica::where('periodo_id', $borrador->periodo_id)
                    ->where('curso_id', $seccion->curso_id)
                    ->where('escuela_programada_id', $seccion->escuela_id)
                    ->where('seccion', $seccion->seccion)
                    ->exists();

                if ($existe) {
                    continue;
                }

                $prog = ProgramacionAcademica::create([
                    'curso_id'            => $seccion->curso_id,
                    'periodo_id'          => $borrador->periodo_id,
                    'docente_id'          => $seccion->docente_id,
                    'aula_id'             => $seccion->aula_id,
                    'grupo_horario_id'    => $seccion->grupo_horario_id,
                    'seccion'             => $seccion->seccion,
                    'ciclo'               => $seccion->ciclo,
                    'capacidad'           => $seccion->capacidad,
                    'n_inscritos'         => 0,
                    'lleno_manual'        => false,
                    'escuela_programada_id' => $seccion->escuela_id,
                    'clave'               => null,
                    'grupo'               => null,
                    'aula'                => null,
                    'n_acta'              => null,
                ]);

                // Asociar la escuela como escuela habilitada
                $prog->escuelas()->attach($seccion->escuela_id);
            }

            $borrador->update([
                'estado'        => 'publicado',
                'publicado_por' => $publicadoPor->id,
                'publicado_at'  => now(),
            ]);

            return $borrador->fresh(['periodo', 'creadoPor', 'publicadoPor']);
        });
    }

    /**
     * Elimina un borrador completo (solo si está en estado 'borrador').
     */
    public function eliminar(BorradorProgramacion $borrador): void
    {
        $this->verificarEditable($borrador);
        $borrador->delete();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function verificarEditable(BorradorProgramacion $borrador): void
    {
        if (!$borrador->esBorrador()) {
            throw new Exception('Este borrador ya fue publicado y no puede modificarse.');
        }
    }

    private function siguienteSeccion(?string $ultima): string
    {
        if (!$ultima) {
            return 'A';
        }
        // Incrementa la última letra: A→B, B→C, ..., Z→A1, etc.
        if (strlen($ultima) === 1) {
            $siguiente = chr(ord($ultima) + 1);
            return $siguiente <= 'Z' ? $siguiente : 'A1';
        }
        // Para A1, A2, etc., incrementa el número
        $letra  = $ultima[0];
        $numero = (int) substr($ultima, 1) + 1;
        return $letra . $numero;
    }
}
