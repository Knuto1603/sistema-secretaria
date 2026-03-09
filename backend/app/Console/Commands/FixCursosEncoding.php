<?php

namespace App\Console\Commands;

use App\Models\Curso;
use Illuminate\Console\Command;

class FixCursosEncoding extends Command
{
    protected $signature = 'cursos:fix-encoding {--dry-run : Muestra los cambios sin aplicarlos}';

    protected $description = 'Corrige nombres de cursos con caracteres corruptos (doble codificación UTF-8/Windows-1252)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Detecta nombres con el patrón típico de doble codificación:
        // bytes UTF-8 tratados como Windows-1252 → Ã, Â, etc.
        $cursos = Curso::where('nombre', 'REGEXP', 'Ã|Â|Á|É|Í|Ó|Ú')->get();

        if ($cursos->isEmpty()) {
            $this->info('No se encontraron cursos con encoding corrupto.');
            return Command::SUCCESS;
        }

        $this->info("Cursos con encoding corrupto encontrados: {$cursos->count()}");
        $this->newLine();

        $headers = ['ID (parcial)', 'Nombre corrupto', 'Nombre corregido'];
        $rows = [];
        $corregidos = 0;

        foreach ($cursos as $curso) {
            $corregido = $this->revertirDobleEncoding($curso->nombre);

            if ($corregido === $curso->nombre) {
                continue;
            }

            $rows[] = [
                substr($curso->id, 0, 8) . '...',
                $curso->nombre,
                $corregido,
            ];

            if (!$dryRun) {
                $curso->update(['nombre' => $corregido]);
                $corregidos++;
            }
        }

        $this->table($headers, $rows);
        $this->newLine();

        if ($dryRun) {
            $this->warn('Modo dry-run: no se realizaron cambios. Ejecuta sin --dry-run para aplicar.');
        } else {
            $this->info("Corregidos: {$corregidos} cursos.");
        }

        return Command::SUCCESS;
    }

    /**
     * Revierte la doble codificación UTF-8 → Windows-1252 → UTF-8.
     *
     * El error ocurrió así:
     *   1. Archivo ya era UTF-8 (ej. "Ñ" = bytes C3 91)
     *   2. mb_convert_encoding lo trató como Windows-1252:
     *      C3 → Ã (U+00C3), 91 → ' (U+2018)
     *   3. Esa cadena se guardó en BD como UTF-8 corrupta.
     *
     * Para revertir: convertir de vuelta a Windows-1252 recupera
     * los bytes originales, que son UTF-8 válido.
     */
    private function revertirDobleEncoding(string $texto): string
    {
        $resultado = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');

        // Verificar que el resultado sea UTF-8 válido antes de devolver
        if (mb_check_encoding($resultado, 'UTF-8')) {
            return $resultado;
        }

        // Si no es válido, devolver el original sin cambios
        return $texto;
    }
}
