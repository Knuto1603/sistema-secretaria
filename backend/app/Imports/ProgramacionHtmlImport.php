<?php

namespace App\Imports;

use App\Models\Aula;
use App\Models\Curso;
use App\Models\Docente;
use App\Models\Escuela;
use App\Models\GrupoHorario;
use App\Models\PlanEstudios;
use App\Models\ProgramacionAcademica;
use DOMDocument;
use DOMXPath;

class ProgramacionHtmlImport
{
    private array $resultados    = [];
    private array $escuelasCache = [];
    private array $grupoCache    = [];
    private array $aulaCache     = [];
    private array $cicloCache    = []; // "curso_id:escuela_id" → ciclo

    public function __construct(private readonly string $periodoId)
    {
        GrupoHorario::all(['id', 'nombre'])->each(function ($g) {
            $this->grupoCache[strtoupper(trim($g->nombre))] = $g->id;
        });

        Aula::all(['id', 'nombre'])->each(function ($a) {
            $this->aulaCache[strtoupper(trim($a->nombre))] = $a->id;
        });
    }

    public function import(string $filePath): void
    {
        $content = file_get_contents($filePath);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rows  = $xpath->query('//body/table/tr');

        foreach ($rows as $row) {
            $this->processRow($row, $xpath);
        }
    }

    private function processRow(\DOMElement $row, DOMXPath $xpath): void
    {
        $dataValues = [];
        foreach ($xpath->query('.//font', $row) as $font) {
            if (strtolower($font->getAttribute('color')) === '000080') {
                $text = trim($font->textContent);
                if ($text !== '') {
                    $dataValues[] = $text;
                }
            }
        }

        if (count($dataValues) < 10) return;

        [$clave, $codigoCurso, $nombreCurso, $grupo, $seccion, $aula, $nombreDocente, $capacidad, $nInscritos, $escuelasText] = $dataValues;

        if (!is_numeric(trim($clave))) return;

        try {
            // Solo buscar por código; el nombre autoritativo viene del plan de estudios
            $curso = Curso::where('codigo', strtoupper(trim($codigoCurso)))->first();
            if (!$curso) return;

            $docente = null;
            $docenteNorm = strtoupper(trim(preg_replace('/\s+/', ' ', $nombreDocente)));
            if ($docenteNorm && !str_contains($docenteNorm, 'CONTRATAR') && !str_contains($docenteNorm, 'ASIGNAR')) {
                $docente = Docente::firstOrCreate(['nombre_completo' => $docenteNorm]);
            }

            $grupoHorarioId = $this->resolverGrupo($grupo);
            $aulaId         = $this->resolverAula($aula);

            $cap = (int) $capacidad;
            if ($cap <= 0) $cap = 40;

            $programacion = ProgramacionAcademica::updateOrCreate(
                ['clave' => trim($clave), 'periodo_id' => $this->periodoId],
                [
                    'curso_id'         => $curso->id,
                    'docente_id'       => $docente?->id,
                    'grupo_horario_id' => $grupoHorarioId,
                    'aula_id'          => $aulaId,
                    'grupo'            => strtoupper(trim($grupo)),
                    'seccion'          => $seccion,
                    'aula'             => strtoupper(trim($aula)),
                    'capacidad'        => $cap,
                    'n_inscritos'      => (int) $nInscritos,
                ]
            );

            $escuelaIds = $this->resolverEscuelas($escuelasText);

            // La primera escuela de la lista es la que programó el curso.
            // Si solo hay una, esa misma es programada y habilitada.
            $escuelaProgramadaId = $escuelaIds[0] ?? null;

            $programacion->escuela_programada_id = $escuelaProgramadaId;
            $programacion->save();

            $programacion->escuelas()->sync($escuelaIds);

            // Ciclo: consultar plan_estudios usando la primera escuela
            $ciclo = null;
            if (!empty($escuelaIds)) {
                $ciclo = $this->resolverCiclo($curso->id, $escuelaIds[0]);
            }
            if ($ciclo !== null) {
                $programacion->ciclo = $ciclo;
                $programacion->save();
            }

            $this->resultados[] = [
                'clave'    => $clave,
                'curso'    => $nombreCurso,
                'estado'   => 'importado',
                'escuelas' => count($escuelaIds),
            ];
        } catch (\Exception $e) {
            $this->resultados[] = [
                'clave'   => $clave,
                'curso'   => $codigoCurso,
                'estado'  => 'error',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    private function resolverGrupo(?string $nombre): ?string
    {
        if (!$nombre || trim($nombre) === '') return null;

        $key = $this->normalizarGrupo($nombre);
        if (isset($this->grupoCache[$key])) return $this->grupoCache[$key];

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

        $key = $this->normalizarAula($nombre);
        if (isset($this->aulaCache[$key])) return $this->aulaCache[$key];

        $aula = Aula::firstOrCreate(
            ['nombre' => $key],
            ['pabellon_id' => null, 'capacidad' => 40, 'activo' => true]
        );

        $this->aulaCache[$key] = $aula->id;
        return $aula->id;
    }

    /**
     * Normaliza el código de grupo del SIGA:
     * "01" → "G1", "14" → "G14", "G1" → "G1"
     */
    private function normalizarGrupo(string $nombre): string
    {
        $nombre = trim($nombre);
        if (preg_match('/^\d+$/', $nombre)) {
            return 'G' . (int) $nombre;
        }
        return strtoupper($nombre);
    }

    /**
     * Normaliza el nombre de aula del SIGA:
     * "PII-A11-3P" → "PII-11"  (quita letra de sala y sufijo de piso)
     * "PII-11"     → "PII-11"  (ya normalizada)
     * "LAB-B3-2P"  → "LAB-3"
     */
    private function normalizarAula(string $nombre): string
    {
        $nombre = strtoupper(trim($nombre));
        // Patrón: PREFIJO-[LETRA]NUMERO[-PISO]
        if (preg_match('/^([A-Z]+)-([A-Z]?)(\d+)(?:-\d+P)?$/', $nombre, $m)) {
            return $m[1] . '-' . $m[3];
        }
        return $nombre;
    }

    private function resolverEscuelas(string $texto): array
    {
        $ids = [];
        foreach (explode('/', $texto) as $parte) {
            $code = $this->mapEscuelaToCode(trim($parte));
            if ($code === null) continue;

            if (!array_key_exists($code, $this->escuelasCache)) {
                $escuela = Escuela::where('codigo', $code)->first();
                $this->escuelasCache[$code] = $escuela?->id;
            }

            if ($this->escuelasCache[$code]) {
                $ids[] = $this->escuelasCache[$code];
            }
        }

        return array_unique($ids);
    }

    private function mapEscuelaToCode(string $nombre): ?string
    {
        $upper = strtoupper($nombre);
        if (str_contains($upper, 'AGROINDUSTR')) return '2';
        if (str_contains($upper, 'MECATRON'))   return '3';
        if (str_contains($upper, 'INFORMATIC')) return '1';
        if (str_contains($upper, 'INDUSTRIAL')) return '0';
        return null;
    }

    private function resolverCiclo(string $cursoId, string $escuelaId): ?int
    {
        $key = "{$cursoId}:{$escuelaId}";

        if (!array_key_exists($key, $this->cicloCache)) {
            $plan = PlanEstudios::where('curso_id', $cursoId)
                ->where('escuela_id', $escuelaId)
                ->first(['ciclo']);
            $this->cicloCache[$key] = $plan?->ciclo;
        }

        return $this->cicloCache[$key];
    }

    public function getResultados(): array { return $this->resultados; }

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
