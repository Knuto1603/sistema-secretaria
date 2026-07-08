<?php

namespace App\Imports;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\ProgramacionAcademica;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importador para la Matriz de Programación Académica.
 *
 * Columnas esperadas (fila 1):
 *   Grupo | Aula | Codigo | Nombre | Seccion | Escuela | E/O | Ciclo | Creditos | ...
 *
 * Diferencias vs. ProgramacionImport (formato SIGA):
 *  - Ciclo ya viene como entero (no romano)
 *  - Sin columnas de docente, capacidad, inscritos ni clave
 *  - Sección viene como "Sec. 1" → se extrae solo el número
 *  - Escuela viene con nombre corto ("Industrial", "Informatica"…)
 */
class ProgramacionMatrizImport implements ToCollection, WithHeadingRow
{
    protected string $periodoId;

    private array $grupoCache   = [];
    private array $aulaCache    = [];
    private array $escuelaCache = [];

    private int   $importados = 0;
    private array $omitidos   = [];

    public function __construct(string $periodoId)
    {
        $this->periodoId = $periodoId;

        GrupoHorario::all(['id', 'nombre'])->each(function ($g) {
            $this->grupoCache[strtoupper(trim($g->nombre))] = $g->id;
        });

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

    public function getResumen(): array
    {
        return [
            'importados' => $this->importados,
            'omitidos'   => count($this->omitidos),
            'detalle'    => $this->omitidos,
        ];
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
     * Extrae el número de sección desde valores como "Sec. 1", "Sec. 2", "1", "A"…
     * Si no hay dígitos devuelve el valor limpio tal cual.
     */
    private function parsearSeccion(?string $valor): ?string
    {
        if (!$valor || trim($valor) === '') return null;

        $valor = trim($valor);

        // "Sec. 1" → "1", "Sec.2" → "2"
        if (preg_match('/sec\.?\s*(\w+)/i', $valor, $m)) {
            return $m[1];
        }

        return $valor;
    }

    private function resolverGrupo(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;

        $key = strtoupper(trim($nombre));

        if (isset($this->grupoCache[$key])) {
            return $this->grupoCache[$key];
        }

        $grupo = GrupoHorario::firstOrCreate(
            ['nombre' => $key],
            ['descripcion' => null, 'activo' => true]
        );

        $this->grupoCache[$key] = $grupo->id;
        return $grupo->id;
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
            $row = [];
            foreach ($rawRow->toArray() as $key => $value) {
                $row[$this->sanitizeKey((string) $key)] = $value;
            }

            $codigoRaw = $row['codigo'] ?? null;
            if (!$codigoRaw || trim((string) $codigoRaw) === '') continue;

            $codigoLimpio = trim((string) $codigoRaw);
            $curso = Curso::where('codigo', $codigoLimpio)->first();

            if (!$curso) {
                $this->omitidos[] = [
                    'codigo'  => $codigoLimpio,
                    'nombre'  => trim((string) ($row['nombre'] ?? '—')),
                    'motivo'  => 'Curso no existe en el sistema',
                ];
                continue;
            }

            $grupoTexto   = $row['grupo']   ?? null;
            $aulaTexto    = $row['aula']    ?? null;
            $escuelaTexto = $row['escuela'] ?? null;
            $seccionTexto = $row['seccion'] ?? null;
            $ciclo        = isset($row['ciclo']) && $row['ciclo'] !== '' ? (int) $row['ciclo'] : null;

            $grupoHorarioId = $this->resolverGrupo($grupoTexto);
            $aulaId         = $this->resolverAula($aulaTexto);
            $escuelaId      = $this->resolverEscuela($escuelaTexto);
            $seccion        = $this->parsearSeccion($seccionTexto);

            // Clave auto-generada (igual que creación manual)
            $id    = (string) Str::uuid();
            $clave = 'M' . strtoupper(substr(str_replace('-', '', $id), 0, 8));

            $prog = ProgramacionAcademica::create([
                'id'                    => $id,
                'curso_id'              => $curso->id,
                'periodo_id'            => $this->periodoId,
                'docente_id'            => null,
                'grupo_horario_id'      => $grupoHorarioId,
                'aula_id'               => $aulaId,
                'clave'                 => $clave,
                'grupo'                 => $grupoTexto ? strtoupper(trim($grupoTexto)) : null,
                'seccion'               => $seccion,
                'ciclo'                 => $ciclo,
                'aula'                  => $aulaTexto ? strtoupper(trim($aulaTexto)) : null,
                'n_acta'                => null,
                'capacidad'             => 40,
                'n_inscritos'           => 0,
                'lleno_manual'          => false,
                'escuela_programada_id' => $escuelaId,
            ]);

            if ($escuelaId) {
                $prog->escuelas()->sync([$escuelaId]);
            }

            $this->importados++;
        }
    }
}
