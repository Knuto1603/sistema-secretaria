<?php

namespace App\Imports;

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Escuela;
use App\Models\ProgramacionAcademica;
use DOMDocument;
use DOMXPath;

class ProgramacionHtmlImport
{
    private array $resultados = [];
    private array $escuelasCache = [];

    public function __construct(private readonly string $periodoId) {}

    public function import(string $filePath): void
    {
        $content = file_get_contents($filePath);
        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');

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
        // Extraer texto de todos los FONT con color de datos (000080)
        $dataValues = [];
        foreach ($xpath->query('.//font', $row) as $font) {
            if (strtolower($font->getAttribute('color')) === '000080') {
                $text = trim($font->textContent);
                if ($text !== '') {
                    $dataValues[] = $text;
                }
            }
        }

        // Estructura esperada: clave, codigo, nombre, grp, secc, aula, docente, cap, n_inscritos, escuelas
        if (count($dataValues) < 10) {
            return;
        }

        [$clave, $codigoCurso, $nombreCurso, $grupo, $seccion, $aula, $nombreDocente, $capacidad, $nInscritos, $escuelasText] = $dataValues;

        // La clave debe ser numérica para ser una fila de datos real
        if (!is_numeric(trim($clave))) {
            return;
        }

        try {
            $curso = Curso::updateOrCreate(
                ['codigo' => strtoupper(trim($codigoCurso))],
                ['nombre' => strtoupper(trim($nombreCurso))]
            );

            $docente = null;
            $docenteNorm = strtoupper(trim(preg_replace('/\s+/', ' ', $nombreDocente)));
            if ($docenteNorm && !str_contains($docenteNorm, 'CONTRATAR') && !str_contains($docenteNorm, 'ASIGNAR')) {
                $docente = Docente::firstOrCreate(['nombre_completo' => $docenteNorm]);
            }

            $programacion = ProgramacionAcademica::updateOrCreate(
                ['clave' => trim($clave), 'periodo_id' => $this->periodoId],
                [
                    'curso_id'    => $curso->id,
                    'docente_id'  => $docente?->id,
                    'grupo'       => $grupo,
                    'seccion'     => $seccion,
                    'aula'        => $aula,
                    'capacidad'   => (int) $capacidad,
                    'n_inscritos' => (int) $nInscritos,
                ]
            );

            $escuelaIds = $this->resolverEscuelas($escuelasText);
            $programacion->escuelas()->sync($escuelaIds);

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

    private function resolverEscuelas(string $texto): array
    {
        $ids = [];
        foreach (explode('/', $texto) as $parte) {
            $code = $this->mapEscuelaToCode(trim($parte));
            if ($code === null) {
                continue;
            }

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

    /**
     * Más específico primero para evitar que "INDUSTRIAL" coincida con "AGROINDUSTRIAL".
     */
    private function mapEscuelaToCode(string $nombre): ?string
    {
        $upper = strtoupper($nombre);
        if (str_contains($upper, 'AGROINDUSTR')) return '2';
        if (str_contains($upper, 'MECATRON'))   return '3';
        if (str_contains($upper, 'INFORMATIC')) return '1';
        if (str_contains($upper, 'INDUSTRIAL')) return '0';
        return null;
    }

    public function getResultados(): array
    {
        return $this->resultados;
    }

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
