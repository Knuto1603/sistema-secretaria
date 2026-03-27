<?php

namespace App\Imports;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\ProgramacionAcademica;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProgramacionImport implements ToCollection, WithHeadingRow
{
    protected string $periodoId;

    /** @var array<string, string> Cache grupo nombre → id */
    private array $grupoCache = [];

    /** @var array<string, string> Cache aula nombre → id */
    private array $aulaCache = [];

    /** @var array<string, string|null> Cache nombre escuela (normalizado) → id|null */
    private array $escuelaCache = [];

    public function __construct(string $periodoId)
    {
        $this->periodoId = $periodoId;

        GrupoHorario::all(['id', 'nombre'])->each(function ($g) {
            $this->grupoCache[strtoupper(trim($g->nombre))] = $g->id;
        });

        Aula::all(['id', 'nombre'])->each(function ($a) {
            $this->aulaCache[strtoupper(trim($a->nombre))] = $a->id;
        });

        // Pre-cargar escuelas: indexar por nombre normalizado y nombre_corto normalizado
        Escuela::all(['id', 'nombre', 'nombre_corto'])->each(function ($e) {
            $this->escuelaCache[$this->normalizar($e->nombre)] = $e->id;
            if ($e->nombre_corto) {
                $this->escuelaCache[$this->normalizar($e->nombre_corto)] = $e->id;
            }
        });
    }

    private function romanToInt(?string $roman): ?int
    {
        if (!$roman || trim($roman) === '') return null;
        $map = ['I'=>1,'V'=>5,'X'=>10,'L'=>50,'C'=>100,'D'=>500,'M'=>1000];
        $roman = strtoupper(trim($roman));
        $result = 0;
        $prev = 0;
        foreach (array_reverse(str_split($roman)) as $char) {
            $val = $map[$char] ?? 0;
            if ($val < $prev) $result -= $val;
            else              $result += $val;
            $prev = $val;
        }
        return $result > 0 ? $result : null;
    }

    private function normalizar(string $texto): string
    {
        $texto = strtoupper(trim($texto));
        // Quitar tildes
        $texto = str_replace(
            ['Á','É','Í','Ó','Ú','Ñ','á','é','í','ó','ú','ñ'],
            ['A','E','I','O','U','N','A','E','I','O','U','N'],
            $texto
        );
        return $texto;
    }

    private function sanitizeKey(string $key): string
    {
        $clean = trim($key, ". ");
        $clean = str_replace(['N°', 'Nº', 'n°', 'nº'], 'n', $clean);
        return Str::slug($clean, '_');
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

    /**
     * Busca el id de una escuela por su nombre (normalizado).
     * Primero intenta match exacto; luego busca si el nombre de la escuela
     * contiene la palabra clave del Excel (ej. "AGROINDUSTRIAL" dentro de
     * "INGENIERIA AGROINDUSTRIAL").
     */
    private function resolverEscuela(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;

        $key = $this->normalizar($nombre);

        if (array_key_exists($key, $this->escuelaCache)) {
            return $this->escuelaCache[$key];
        }

        // Buscar escuela cuyo nombre normalizado contiene la palabra clave
        $encontrado = null;
        foreach ($this->escuelaCache as $nombreNorm => $id) {
            if (str_contains($nombreNorm, $key) || str_contains($key, $nombreNorm)) {
                $encontrado = $id;
                break;
            }
        }

        // Guardar resultado (null si no se encontró) para no repetir búsqueda
        $this->escuelaCache[$key] = $encontrado;
        return $encontrado;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $rawRow) {
            // Normalizar keys del encabezado
            $row = [];
            foreach ($rawRow->toArray() as $key => $value) {
                $row[$this->sanitizeKey((string) $key)] = $value;
            }

            $codigoRaw = $row['codigo'] ?? null;
            if (!$codigoRaw) continue;

            $curso = Curso::where('codigo', trim($codigoRaw))->first();
            if (!$curso) continue;

            // Resolver FKs
            $grupoNombreTexto = $row['grp'] ?? null;
            $aulaNombreTexto  = $row['aula'] ?? null;
            $docenteNombre    = $row['docente'] ?? null;
            $escuelaNombre    = $row['escuela'] ?? null;

            $grupoHorarioId = $this->resolverGrupo($grupoNombreTexto);
            $aulaId         = $this->resolverAula($aulaNombreTexto);
            $escuelaId      = $this->resolverEscuela($escuelaNombre);
            $cicloInt       = $this->romanToInt($row['ciclo'] ?? null);

            $docente = null;
            if ($docenteNombre && strtoupper(trim($docenteNombre)) !== 'POR ASIGNAR') {
                $docente = Docente::firstOrCreate(['nombre_completo' => trim(strtoupper($docenteNombre))]);
            }

            $capacidad = (int) ($row['cap'] ?? 0);
            if ($capacidad <= 0) $capacidad = 40;

            $prog = ProgramacionAcademica::create([
                'curso_id'            => $curso->id,
                'periodo_id'          => $this->periodoId,
                'docente_id'          => $docente?->id,
                'grupo_horario_id'    => $grupoHorarioId,
                'aula_id'             => $aulaId,
                'clave'               => $row['clave'] ?? 'S/N',
                'grupo'               => $grupoNombreTexto ? strtoupper(trim($grupoNombreTexto)) : 'A',
                'seccion'             => $row['sec'] ?? null,
                'ciclo'               => $cicloInt,
                'aula'                => $aulaNombreTexto ? strtoupper(trim($aulaNombreTexto)) : null,
                'n_acta'              => $row['n_acta'] ?? null,
                'capacidad'           => $capacidad,
                'n_inscritos'         => (int) ($row['n_inscritos'] ?? 0),
                // La escuela del Excel es también la responsable de programar el curso
                'escuela_programada_id' => $escuelaId,
            ]);

            // Sincronizar escuela habilitada
            if ($escuelaId) {
                $prog->escuelas()->sync([$escuelaId]);
            }
        }
    }
}
