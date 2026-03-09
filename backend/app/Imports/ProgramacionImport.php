<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Aula;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\GrupoHorario;
use App\Models\ProgramacionAcademica;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class ProgramacionImport implements ToModel, WithHeadingRow
{
    protected string $periodoId;

    /** @var array<string, string> Cache grupo nombre → id */
    private array $grupoCache = [];

    /** @var array<string, string> Cache aula nombre → id */
    private array $aulaCache = [];

    public function __construct(string $periodoId)
    {
        $this->periodoId = $periodoId;

        // Pre-cargar grupos y aulas existentes en cache
        GrupoHorario::all(['id', 'nombre'])->each(function ($g) {
            $this->grupoCache[strtoupper(trim($g->nombre))] = $g->id;
        });

        Aula::all(['id', 'nombre'])->each(function ($a) {
            $this->aulaCache[strtoupper(trim($a->nombre))] = $a->id;
        });
    }

    private function sanitizeKey($key): string
    {
        $clean = trim($key, ". ");
        $clean = str_replace(['N°', 'Nº', 'n°', 'nº'], 'n', $clean);
        return Str::slug($clean, '_');
    }

    /**
     * Busca o crea un GrupoHorario por nombre (ej: G1, G2...).
     * Retorna el id o null si el nombre está vacío.
     */
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

    /**
     * Busca o crea un Aula por nombre (ej: PII-04, A-26...).
     * pabellon_id es null; el admin lo asignará desde configuración.
     * Retorna el id o null si el nombre está vacío.
     */
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

    public function model(array $row)
    {
        $cleanRow = [];
        foreach ($row as $key => $value) {
            $cleanRow[$this->sanitizeKey($key)] = $value;
        }

        if (!isset($cleanRow['codigo']) || !isset($cleanRow['nombre_del_curso'])) {
            return null;
        }

        // Área
        $areaName = isset($cleanRow['area']) ? trim(strtoupper($cleanRow['area'])) : 'SIN AREA';
        $area = Area::firstOrCreate(['nombre' => $areaName]);

        // Docente
        $docente = null;
        $nombreDocente = $cleanRow['docente'] ?? null;
        if ($nombreDocente && strtoupper(trim($nombreDocente)) !== 'POR ASIGNAR') {
            $docente = Docente::firstOrCreate(['nombre_completo' => trim(strtoupper($nombreDocente))]);
        }

        // Curso
        $curso = Curso::where('codigo', trim($cleanRow['codigo']))->first();
        if (!$curso) return null;

        if (!$curso->area_id) {
            $curso->update(['area_id' => $area->id]);
        }

        // Resolver FKs de grupo y aula
        $grupoNombreTexto = $cleanRow['grp'] ?? null;
        $aulaNombreTexto  = $cleanRow['aula'] ?? null;

        $grupoHorarioId = $this->resolverGrupo($grupoNombreTexto);
        $aulaId         = $this->resolverAula($aulaNombreTexto);
        $grupoNombre    = $grupoNombreTexto ? strtoupper(trim($grupoNombreTexto)) : null;

        // Capacidad: mínimo 40 si no se especifica o es 0
        $capacidad = (int) ($cleanRow['cap'] ?? 0);
        if ($capacidad <= 0) $capacidad = 40;

        return new ProgramacionAcademica([
            'curso_id'         => $curso->id,
            'periodo_id'       => $this->periodoId,
            'docente_id'       => $docente?->id,
            'grupo_horario_id' => $grupoHorarioId,
            'aula_id'          => $aulaId,
            'clave'            => $cleanRow['clave'] ?? 'S/N',
            'grupo'            => $grupoNombre ?? 'A',
            'seccion'          => $cleanRow['sec'] ?? null,
            'aula'             => $aulaNombreTexto ? strtoupper(trim($aulaNombreTexto)) : null,
            'n_acta'           => $cleanRow['n_acta'] ?? null,
            'capacidad'        => $capacidad,
            'n_inscritos'      => (int) ($cleanRow['n_inscritos'] ?? 0),
        ]);
    }
}
