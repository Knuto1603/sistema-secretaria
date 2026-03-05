<?php

namespace App\Imports;

use App\Models\Curso;
use App\Models\Escuela;
use App\Models\PlanEstudios;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PlanEstudiosImport implements ToCollection, WithHeadingRow
{
    private array $resultados = [];

    public function __construct(private readonly string $escuelaCodigo) {}

    public function collection(Collection $rows): void
    {
        $escuela = Escuela::findByCodigo($this->escuelaCodigo);

        if (! $escuela) {
            throw new \InvalidArgumentException("Escuela con código '{$this->escuelaCodigo}' no encontrada.");
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

                // Upsert: si ya existe para esta escuela, actualiza los datos
                PlanEstudios::updateOrCreate(
                    ['escuela_id' => $escuela->id, 'curso_id' => $curso->id],
                    [
                        'ciclo'    => $row['ciclo'] ?? null,
                        'creditos' => $row['creditos'] ?? null,
                        'tipo'     => $tipo,
                    ]
                );

                $this->resultados[] = [
                    'fila'    => $fila,
                    'codigo'  => $codigoCurso,
                    'estado'  => 'importado',
                    'mensaje' => $nombreCurso,
                ];
            } catch (\Exception $e) {
                $this->resultados[] = [
                    'fila'    => $fila,
                    'codigo'  => $row['codigo_curso'] ?? '?',
                    'estado'  => 'error',
                    'mensaje' => $e->getMessage(),
                ];
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
