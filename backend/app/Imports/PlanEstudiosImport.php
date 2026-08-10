<?php

namespace App\Imports;

use App\Models\Curso;
use App\Models\Escuela;
use App\Models\Plan;
use App\Models\PlanEstudios;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PlanEstudiosImport implements ToCollection, WithHeadingRow
{
    private array $resultados  = [];
    private array $requisitosMap = []; // ['CODIGO_CURSO' => ['REQ1', 'REQ2']]
    private array $resultadoIndexPorCurso = []; // ['CODIGO_CURSO' => índice en $resultados]

    public function __construct(
        private readonly string $escuelaCodigo,
        private readonly ?string $planId = null
    ) {}

    public function collection(Collection $rows): void
    {
        $escuela = Escuela::findByCodigo($this->escuelaCodigo);

        if (! $escuela) {
            throw new \InvalidArgumentException("Escuela con código '{$this->escuelaCodigo}' no encontrada.");
        }

        // Resolver plan activo si no se especificó uno
        $planId = $this->planId;
        if (!$planId) {
            $plan = Plan::where('escuela_id', $escuela->id)->where('activo', true)->first();
            $planId = $plan?->id;
        }

        foreach ($rows as $index => $row) {
            $fila = $index + 2;

            try {

                $codigoCurso = trim(strtoupper((string) $row['codigo_curso']));
                $nombreCurso = isset($row['nombre_curso']) ? trim(strtoupper((string) $row['nombre_curso'])) : null;

                if (empty($codigoCurso)) {
                    continue;
                }

                if (empty($nombreCurso)) {
                    $this->resultados[] = [
                        'fila'    => $fila,
                        'codigo'  => $codigoCurso,
                        'estado'  => 'error',
                        'mensaje' => "La columna 'nombre_curso' es obligatoria",
                    ];
                    continue;
                }

                // Crear o actualizar el curso (el plan es la fuente de verdad)
                $curso = Curso::updateOrCreate(
                    ['codigo' => $codigoCurso],
                    ['nombre' => $nombreCurso]
                );

                $tipo = strtoupper(trim((string) ($row['tipo'] ?? 'O')));
                if (! in_array($tipo, ['O', 'E'])) {
                    $tipo = 'O';
                }

                // Códigos de requisito de esta fila (se guardan tal cual para mostrarlos en
                // la UI, y además se resuelven al final contra cursos reales para la relación).
                $codigoRequisito = trim((string) ($row['requisitos'] ?? ''));
                $codigos = $codigoRequisito !== ''
                    ? array_values(array_filter(array_map('trim', explode(',', $codigoRequisito))))
                    : [];
                if (!empty($codigos)) {
                    $this->requisitosMap[$codigoCurso] = $codigos;
                }

                $updateData = [
                    'escuela_id'      => $escuela->id,
                    'ciclo'           => $row['ciclo'] ?? null,
                    'creditos'        => $row['creditos'] ?? null,
                    'tipo'            => $tipo,
                    'horas_teoricas'  => $row['ht_semanal'] ?? null,
                    'horas_practicas' => $row['hp_semanal'] ?? null,
                    'requisitos'      => $codigos,
                ];
                if ($planId) {
                    $updateData['plan_id'] = $planId;
                }

                // Upsert: clave por plan_id+curso_id si hay plan, si no por escuela_id+curso_id
                $whereClause = $planId
                    ? ['plan_id' => $planId, 'curso_id' => $curso->id]
                    : ['escuela_id' => $escuela->id, 'curso_id' => $curso->id];

                PlanEstudios::updateOrCreate($whereClause, $updateData);

                $this->resultados[] = [
                    'fila'    => $fila,
                    'codigo'  => $codigoCurso,
                    'estado'  => 'importado',
                    'mensaje' => $nombreCurso,
                ];
                $this->resultadoIndexPorCurso[$codigoCurso] = count($this->resultados) - 1;
            } catch (\Exception $e) {
                $this->resultados[] = [
                    'fila'    => $fila,
                    'codigo'  => $row['codigo_curso'] ?? '?',
                    'estado'  => 'error',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        $this->resolverRequisitos();
    }

    /**
     * Segunda pasada: sincronizar requisitos ahora que todos los cursos ya existen.
     */
    private function resolverRequisitos(): void
    {
        foreach ($this->requisitosMap as $cursoCodigo => $requisitosCodigos) {
            $curso = Curso::where('codigo', $cursoCodigo)->first();
            if (!$curso) {
                continue;
            }

            $cursosRequisito = Curso::whereIn('codigo', $requisitosCodigos)->get(['id', 'codigo']);
            $curso->requisitos()->sync($cursosRequisito->pluck('id')->toArray());

            // Códigos de requisito que no corresponden a ningún curso (p.ej. "100cred.",
            // requisitos por créditos acumulados en vez de por curso): se ignoran, pero se
            // anota en el mensaje de la fila del curso para que quede visible en el resultado.
            $noResueltos = array_diff($requisitosCodigos, $cursosRequisito->pluck('codigo')->toArray());
            if (!empty($noResueltos) && isset($this->resultadoIndexPorCurso[$cursoCodigo])) {
                $idx = $this->resultadoIndexPorCurso[$cursoCodigo];
                $this->resultados[$idx]['mensaje'] .= ' (requisito(s) no reconocido(s), ignorados: ' . implode(', ', $noResueltos) . ')';
            }
        }
    }

    public function getResultados(): array
    {
        return $this->resultados;
    }

    public function getResumen(): array
    {
        $importados = collect($this->resultados)->where('estado', 'importado')->count();
        $errores    = collect($this->resultados)->where('estado', 'error')->count();

        return [
            'total'      => count($this->resultados),
            'importados' => $importados,
            'errores'    => $errores,
        ];
    }
}
