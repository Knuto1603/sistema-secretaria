<?php

namespace App\Imports;

use App\Models\Aula;
use App\Models\BorradorSeccion;
use App\Models\Curso;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Crea BorradorSeccion a partir de la Matriz de Programación Académica.
 *
 * Columnas esperadas (fila 1):
 *   Grupo | Aula | Codigo | Nombre | Seccion | Escuela | E/O | Ciclo | ...
 */
class BorradorMatrizImport implements ToCollection, WithHeadingRow
{
    private array $grupoCache   = [];
    private array $aulaCache    = [];
    private array $escuelaCache = [];

    private int   $importados = 0;
    private array $omitidos   = [];

    public function __construct(
        private readonly string $borradorId
    ) {
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

    private function parsearSeccion(?string $valor): string
    {
        if (!$valor || trim($valor) === '') return '1';

        $valor = trim($valor);

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
                    'codigo' => $codigoLimpio,
                    'nombre' => trim((string) ($row['nombre'] ?? '—')),
                    'motivo' => 'Curso no existe en el sistema',
                ];
                continue;
            }

            $escuelaId = $this->resolverEscuela($row['escuela'] ?? null);

            if (!$escuelaId) {
                $this->omitidos[] = [
                    'codigo' => $codigoLimpio,
                    'nombre' => $curso->nombre,
                    'motivo' => 'Escuela "' . ($row['escuela'] ?? '') . '" no encontrada',
                ];
                continue;
            }

            $grupoHorarioId = $this->resolverGrupo($row['grupo'] ?? null);
            $aulaId         = $this->resolverAula($row['aula'] ?? null);
            $seccion        = $this->parsearSeccion($row['seccion'] ?? null);
            $ciclo          = isset($row['ciclo']) && $row['ciclo'] !== '' ? (int) $row['ciclo'] : null;
            $tipoRaw        = strtoupper(trim((string) ($row['e_o'] ?? 'O')));
            $tipo           = in_array($tipoRaw, ['O', 'E']) ? $tipoRaw : 'O';

            BorradorSeccion::create([
                'programacion_id'      => $this->borradorId,
                'curso_id'             => $curso->id,
                'escuela_programada_id' => $escuelaId,
                'ciclo'                => $ciclo,
                'tipo'                 => $tipo,
                'seccion'              => $seccion,
                'docente_id'           => null,
                'aula_id'              => $aulaId,
                'grupo_horario_id'     => $grupoHorarioId,
                'capacidad'            => 40,
            ]);

            $this->importados++;
        }
    }
}
