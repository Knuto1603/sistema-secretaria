<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\ProgramacionAcademica;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class ProgramacionImport implements ToModel, WithHeadingRow
{
    protected $periodoId;

    public function __construct($periodoId)
    {
        $this->periodoId = $periodoId;
    }

    /**
     * Limpia y normaliza los encabezados del Excel para evitar errores por
     * espacios, puntos o caracteres especiales como N°.
     */
    private function sanitizeKey($key)
    {
        // 1. Eliminar puntos iniciales o finales y espacios extra
        $clean = trim($key, ". ");

        // 2. Reemplazar el símbolo N° o Nº por 'n' para consistencia
        $clean = str_replace(['N°', 'Nº', 'n°', 'nº'], 'n', $clean);

        // 3. Convertir a "slug" (ej: "Nombre del Curso" -> "nombre_del_curso")
        return Str::slug($clean, '_');
    }

    public function model(array $row)
    {
        // Aplicamos la limpieza a todas las llaves del array de la fila actual
        $cleanRow = [];
        foreach ($row as $key => $value) {
            $cleanRow[$this->sanitizeKey($key)] = $value;
        }

        // --- VALIDACIÓN DE DATOS MÍNIMOS ---
        // Buscamos las llaves ya limpias
        if (!isset($cleanRow['codigo']) || !isset($cleanRow['nombre_del_curso'])) {
            return null;
        }

        // --- PROCESAMIENTO NORMALIZADO ---

        // 1. Área
        $areaName = isset($cleanRow['area']) ? trim(strtoupper($cleanRow['area'])) : 'SIN AREA';
        $area = Area::firstOrCreate(['nombre' => $areaName]);

        // 2. Docente (Maneja "docente_teoria")
        $docente = null;
        $nombreDocente = $cleanRow['docente'] ?? null;
        if ($nombreDocente && strtoupper(trim($nombreDocente)) !== 'POR ASIGNAR') {
            $docente = Docente::firstOrCreate(['nombre_completo' => trim(strtoupper($nombreDocente))]);
        }

        // 3. Curso — solo busca existentes (los cursos los crea el plan de estudios)
        $curso = Curso::where('codigo', trim($cleanRow['codigo']))->first();

        if (! $curso) {
            return null; // Saltar filas cuyo curso no esté en ningún plan de estudios
        }

        // Enriquecer el curso con el área si aún no la tiene
        if (! $curso->area_id) {
            $curso->update(['area_id' => $area->id]);
        }

        // 4. Programación Académica
        // Usamos las llaves normalizadas por nuestra función sanitizeKey
        return new ProgramacionAcademica([
            'curso_id'    => $curso->id,
            'periodo_id'  => $this->periodoId,
            'docente_id'  => $docente?->id,
            'clave'       => $cleanRow['clave'] ?? 'S/N',
            'grupo'       => $cleanRow['grp'] ?? 'A',
            'seccion'     => $cleanRow['sec'] ?? null,
            'aula'        => $cleanRow['aula'] ?? null,
            'n_acta'      => $cleanRow['n_acta'] ?? null,
            'capacidad'   => (int) ($cleanRow['cap'] ?? 0),
            'n_inscritos' => (int) ($cleanRow['n_inscritos'] ?? 0),
        ]);
    }
}
