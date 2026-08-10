<?php

namespace App\Imports;

use App\Models\Escuela;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Importa el reporte SIGA "Matriculados por Periodo y Promoción" (Excel).
 *
 * Formato del reporte: filas de metadata (título, periodo, facultad, etc.)
 * seguidas de una tabla con cabecera Código | Documento | Nombres y Apellidos |
 * Facultad | Escuela Profesional | Promoción. La escuela y el año de ingreso
 * se derivan del propio código universitario (más confiable que el texto de
 * las columnas, que puede llegar con problemas de codificación).
 *
 * La contraseña inicial del estudiante es su número de documento (DNI).
 */
class EstudianteReporteMatriculaImport
{
    private array $resultados = [];
    private ?Role $rolEstudiante = null;

    /**
     * @param array $rows Filas crudas del sheet (una por índice, cada una un array de celdas)
     */
    public function procesar(array $rows): void
    {
        $this->rolEstudiante = Role::where('name', 'estudiante')->where('guard_name', 'web')->first();

        $enTabla = false;

        foreach ($rows as $index => $row) {
            $fila = $index + 1; // Maatwebsite Excel::toArray es 0-indexed
            $row  = array_values($row);

            if (!$enTabla) {
                // La cabecera real de la tabla trae 'Documento' en la segunda columna
                if (isset($row[1]) && mb_strtolower(trim((string) $row[1])) === 'documento') {
                    $enTabla = true;
                }
                continue;
            }

            $codigo = trim((string) ($row[0] ?? ''));

            // Fin de la tabla (fila vacía o sin código de 10 dígitos)
            if (!preg_match('/^\d{10}$/', $codigo)) {
                break;
            }

            $this->procesarFila($fila, $codigo, $row);
        }
    }

    private function procesarFila(int $fila, string $codigo, array $row): void
    {
        $documento = trim((string) ($row[1] ?? ''));
        $nombreCrudo = trim((string) ($row[2] ?? ''));

        if ($documento === '' || $nombreCrudo === '') {
            $this->resultados[] = [
                'fila'    => $fila,
                'codigo'  => $codigo,
                'estado'  => 'error',
                'mensaje' => 'Falta documento o nombre.',
            ];
            return;
        }

        if (User::where('codigo_universitario', $codigo)->exists()) {
            $this->resultados[] = [
                'fila'    => $fila,
                'codigo'  => $codigo,
                'estado'  => 'omitido',
                'mensaje' => 'El código ya existe.',
            ];
            return;
        }

        $escuelaDigito = substr($codigo, 2, 1);
        $escuela = Escuela::findByCodigo($escuelaDigito);
        if (!$escuela) {
            $this->resultados[] = [
                'fila'    => $fila,
                'codigo'  => $codigo,
                'estado'  => 'error',
                'mensaje' => "Escuela código '{$escuelaDigito}' no válida.",
            ];
            return;
        }

        try {
            $user = User::create([
                'name'                 => $this->formatearNombre($nombreCrudo),
                'codigo_universitario' => $codigo,
                'email'                => User::generarEmailEstudiante($codigo),
                'escuela_id'           => $escuela->id,
                'tipo_usuario'         => 'estudiante',
                'password'             => Hash::make($documento),
                'password_set_at'      => now(),
                'must_change_password' => true,
                'activo'               => true,
            ]);

            $user->asignarDatosDesdeCodigoUniversitario();
            $user->save();

            if ($this->rolEstudiante) {
                $user->assignRole($this->rolEstudiante);
            }

            $this->resultados[] = [
                'fila'    => $fila,
                'codigo'  => $codigo,
                'estado'  => 'importado',
                'mensaje' => 'Creado correctamente. Password inicial: DNI.',
            ];
        } catch (\Exception $e) {
            $this->resultados[] = [
                'fila'    => $fila,
                'codigo'  => $codigo,
                'estado'  => 'error',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convierte "APELLIDOS, NOMBRES" a "Nombres Apellidos" en formato Título.
     */
    private function formatearNombre(string $nombre): string
    {
        $partes = explode(',', $nombre, 2);

        if (count($partes) === 2) {
            $nombre = trim($partes[1]) . ' ' . trim($partes[0]);
        }

        return mb_convert_case(trim($nombre), MB_CASE_TITLE, 'UTF-8');
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
