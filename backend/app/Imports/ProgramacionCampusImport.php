<?php

namespace App\Imports;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\ProgramacionAcademica;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Importador para el reporte de Programación Académica del sistema Campus.
 *
 * Diferencias con el formato SIGA:
 *  - Encabezado en la fila 8 (no en la fila 1)
 *  - Columna "Ciclo" ya es entero (no número romano)
 *  - Inscritos en columna "N. Inscr." (clave: n_inscr)
 *  - Sin columna "Clave"
 *  - Marca lleno_manual = true cuando n_inscritos >= capacidad
 */
class ProgramacionCampusImport implements ToCollection, WithHeadingRow
{
    protected string $periodoId;

    private array $aulaCache    = [];
    private array $escuelaCache = [];

    /** Registros del Campus que no pudieron actualizarse (no existen en sistema) */
    private array $omitidos = [];
    private int   $actualizados = 0;
    /** IDs de programaciones actualizadas exitosamente */
    private array $actualizadosIds = [];

    public function getResumen(): array
    {
        // Programaciones del periodo que Campus NO actualizó → no están en Campus
        $noEnCampus = ProgramacionAcademica::where('periodo_id', $this->periodoId)
            ->whereNotIn('id', $this->actualizadosIds)
            ->with(['curso:id,codigo,nombre', 'escuelas:id,nombre,nombre_corto'])
            ->get()
            ->map(fn($p) => [
                'id'      => $p->id,
                'codigo'  => $p->curso?->codigo,
                'nombre'  => $p->curso?->nombre,
                'seccion' => $p->seccion,
                'grupo'   => $p->grupo,
                'escuela' => $p->escuelas->first()?->nombre_corto ?? $p->escuelas->first()?->nombre ?? '—',
            ])
            ->values()
            ->toArray();

        return [
            'actualizados'  => $this->actualizados,
            'omitidos'      => count($this->omitidos),
            'detalle'       => $this->omitidos,
            'no_en_campus'  => $noEnCampus,
        ];
    }

    public function __construct(string $periodoId)
    {
        $this->periodoId = $periodoId;

        Aula::all(['id', 'nombre'])->each(function ($a) {
            $this->aulaCache[strtoupper(trim($a->nombre))] = $a->id;
        });

        Escuela::all(['id', 'nombre', 'nombre_corto'])->each(function ($e) {
            $this->escuelaCache[$this->normalizar($e->nombre)] = $e->id;
            if ($e->nombre_corto) {
                $this->escuelaCache[$this->normalizar($e->nombre_corto)] = $e->id;
            }
        });
    }

    /**
     * El encabezado del reporte Campus está en la fila 8.
     */
    public function headingRow(): int
    {
        return 8;
    }

    private function normalizar(string $texto): string
    {
        $texto = strtoupper(trim($texto));
        return str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['A', 'E', 'I', 'O', 'U', 'N', 'A', 'E', 'I', 'O', 'U', 'N'],
            $texto
        );
    }

    private function sanitizeKey(string $key): string
    {
        $clean = trim($key, '. ');
        $clean = str_replace(['N°', 'Nº', 'n°', 'nº'], 'n', $clean);
        return Str::slug($clean, '_');
    }

    /**
     * Normaliza el nombre de grupo eliminando sufijos de turno/sección.
     * G1A → G1, G10AB → G10, G3AH → G3, G5BH → G5, G1ABH → G1
     */
    private function normalizarGrupo(string $nombre): string
    {
        $nombre = strtoupper(trim($nombre));
        if (preg_match('/^(G\d+)[A-Z]+$/', $nombre, $m)) {
            return $m[1];
        }
        return $nombre;
    }

    private function resolverAula(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;

        $key = strtoupper(trim($nombre));

        if (isset($this->aulaCache[$key])) {
            return $this->aulaCache[$key];
        }

        $aula = Aula::firstOrCreate(
            ['nombre' => $key],
            ['pabellon_id' => null, 'capacidad' => 40, 'activo' => true]
        );

        $this->aulaCache[$key] = $aula->id;
        return $aula->id;
    }

    private function resolverEscuela(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;

        $key = $this->normalizar($nombre);

        if (array_key_exists($key, $this->escuelaCache)) {
            return $this->escuelaCache[$key];
        }

        $encontrado = null;
        foreach ($this->escuelaCache as $nombreNorm => $id) {
            if (str_contains($nombreNorm, $key) || str_contains($key, $nombreNorm)) {
                $encontrado = $id;
                break;
            }
        }

        $this->escuelaCache[$key] = $encontrado;
        return $encontrado;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $rawRow) {
            // Normalizar keys del encabezado con el mismo sanitizeKey
            $row = [];
            foreach ($rawRow->toArray() as $key => $value) {
                $row[$this->sanitizeKey((string) $key)] = $value;
            }

            // Columna "Código" → clave sanitizada "codigo"
            $codigoRaw = $row['codigo'] ?? null;
            if (!$codigoRaw || trim((string) $codigoRaw) === '') continue;

            $codigoLimpio = trim((string) $codigoRaw);
            $curso = Curso::where('codigo', $codigoLimpio)->first();
            if (!$curso) {
                $this->omitidos[] = [
                    'codigo'  => $codigoLimpio,
                    'nombre'  => trim((string) ($row['nombre'] ?? $row['asignatura'] ?? '—')),
                    'seccion' => $row['sec'] ?? null,
                    'motivo'  => 'Curso no existe en el sistema',
                ];
                continue;
            }

            $grupoNombreTexto = $row['grp']      ?? null;
            $aulaNombreTexto  = $row['aula']     ?? null;
            $escuelaNombre    = $row['escuela']  ?? null;

            $grupoNombreNorm = $grupoNombreTexto ? $this->normalizarGrupo($grupoNombreTexto) : null;
            $aulaId          = $this->resolverAula($aulaNombreTexto);
            $escuelaId       = $this->resolverEscuela($escuelaNombre);

            $capacidad  = (int) ($row['cap'] ?? 0);
            if ($capacidad <= 0) $capacidad = 40;

            // n_inscr es la clave resultante de sanitizeKey("N. Inscr.")
            $nInscritos  = (int) ($row['n_inscr'] ?? 0);

            // Marcar como lleno manual si ya está completo según Campus
            $llenoManual = $nInscritos >= $capacidad;

            // Buscar por curso + grupo (normalizado) + aula + escuela para evitar colisiones
            $query = ProgramacionAcademica::where('periodo_id', $this->periodoId)
                ->where('curso_id', $curso->id);

            if ($grupoNombreNorm) {
                $query->where('grupo', $grupoNombreNorm);
            }
            if ($aulaId) {
                $query->where('aula_id', $aulaId);
            }
            if ($escuelaId) {
                $query->whereExists(function ($q) use ($escuelaId) {
                    $q->from('programacion_escuelas')
                      ->whereColumn('programacion_escuelas.programacion_id', 'programacion_academica.id')
                      ->where('programacion_escuelas.escuela_id', $escuelaId);
                });
            }

            $prog = $query->first();

            if (!$prog) {
                $this->omitidos[] = [
                    'codigo'  => $codigoLimpio,
                    'nombre'  => $curso->nombre,
                    'seccion' => $row['sec'] ?? null,
                    'motivo'  => "No coincide curso+grupo({$grupoNombreNorm})+aula+escuela en el periodo",
                ];
                continue;
            }

            // Campus solo actualiza inscritos y cupos
            $prog->update([
                'capacidad'    => $capacidad,
                'n_inscritos'  => $nInscritos,
                'lleno_manual' => $llenoManual,
            ]);

            $this->actualizadosIds[] = $prog->id;
            $this->actualizados++;
        }
    }
}
