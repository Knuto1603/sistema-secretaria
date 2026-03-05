<?php

namespace App\Imports;

use App\Models\Escuela;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class EstudianteImport implements ToCollection, WithHeadingRow, WithValidation
{
    private array $resultados = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $fila = $index + 2; // +2 porque la fila 1 es el header

            try {
                $codigo = trim((string) $row['codigo_universitario']);

                // Verificar si ya existe
                if (User::where('codigo_universitario', $codigo)->exists()) {
                    $this->resultados[] = [
                        'fila'    => $fila,
                        'codigo'  => $codigo,
                        'estado'  => 'omitido',
                        'mensaje' => 'El código ya existe',
                    ];
                    continue;
                }

                // Buscar escuela por código
                $escuela = Escuela::findByCodigo((string) $row['escuela_codigo']);
                if (! $escuela) {
                    $this->resultados[] = [
                        'fila'    => $fila,
                        'codigo'  => $codigo,
                        'estado'  => 'error',
                        'mensaje' => "Escuela código '{$row['escuela_codigo']}' no válida",
                    ];
                    continue;
                }

                User::create([
                    'name'                  => trim((string) $row['nombre']),
                    'codigo_universitario'  => $codigo,
                    'escuela_id'            => $escuela->id,
                    'anio_ingreso'          => $row['anio_ingreso'] ? (int) $row['anio_ingreso'] : null,
                    'tipo_usuario'          => 'estudiante',
                    'password'              => Hash::make($codigo), // password temporal = código
                    'activo'                => true,
                ]);

                $this->resultados[] = [
                    'fila'    => $fila,
                    'codigo'  => $codigo,
                    'estado'  => 'importado',
                    'mensaje' => 'Creado correctamente',
                ];
            } catch (\Exception $e) {
                $this->resultados[] = [
                    'fila'    => $fila,
                    'codigo'  => $row['codigo_universitario'] ?? '?',
                    'estado'  => 'error',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }
    }

    public function rules(): array
    {
        return [
            '*.codigo_universitario' => ['required', 'digits:10'],
            '*.nombre'               => ['required', 'string', 'max:255'],
            '*.escuela_codigo'       => ['required', 'in:0,1,2,3'],
            '*.anio_ingreso'         => ['nullable', 'integer', 'min:2000', 'max:2099'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            '*.codigo_universitario.required' => 'La fila :position requiere código universitario.',
            '*.codigo_universitario.digits'   => 'El código universitario debe tener exactamente 10 dígitos (fila :position).',
            '*.nombre.required'               => 'El nombre es obligatorio (fila :position).',
            '*.escuela_codigo.required'       => 'El código de escuela es obligatorio (fila :position).',
            '*.escuela_codigo.in'             => 'El código de escuela debe ser 0, 1, 2 o 3 (fila :position).',
        ];
    }

    public function getResultados(): array
    {
        return $this->resultados;
    }

    public function getResumen(): array
    {
        $importados = collect($this->resultados)->where('estado', 'importado')->count();
        $omitidos   = collect($this->resultados)->where('estado', 'omitido')->count();
        $errores    = collect($this->resultados)->where('estado', 'error')->count();

        return [
            'total'      => count($this->resultados),
            'importados' => $importados,
            'omitidos'   => $omitidos,
            'errores'    => $errores,
        ];
    }
}
