<?php

namespace App\Imports;

use Smalot\PdfParser\Parser;

/**
 * Parser de PDFs de Plan de Estudios del SIGA-UNP.
 *
 * Formato de encabezado de ciclo:
 *   ICICLO:   IICICLO:   IIICICLO:   IVCICLO:   VCICLO:   VICICLO:
 *   VIICICLO:   VIIICICLO:   IXCICLO:   XCICLO:
 *   (el numeral romano se pega directamente a "CICLO:")
 *
 * Formato de columnas por fila de curso:
 *   CODIGO   NOMBRE [2+ espacios] TIPO [REQUISITOS]   CRED   HT   HP
 *   Ejemplo sin req:   "ED1292 ACTIVIDAD DEPORTIVA    O2 0 64"
 *   Ejemplo con req:   "MA1435 CALCULO I    OMA1408    4 48 32"
 *   Ejemplo multi-req: "MA2333 ALGEBRA LINEAL    OMA1435 / MA1470    3 32 32"
 *   Ejemplo req-cred:  "ED3285 TALLER ...    O100cred./ ED1331    2 0 64"
 *
 * Resumen de créditos (columnas separadas por smalot):
 *   Créditos Obligatorios:          213
 *   Créditos Electivos:              15
 *   Créditos de Prácticas:            0
 *   Otros Créditos:                   0
 */
class PlanEstudiosPdfImport
{
    /**
     * @return array{
     *   plan_nombre: string,
     *   escuela_nombre: string,
     *   total_creditos_obligatorios: int,
     *   creditos_electivos_requeridos: int,
     *   cursos: array
     * }
     */
    public function parse(string $filePath): array
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();

        [$obligatorios, $electivos] = $this->extractResumenCreditos($text);

        return [
            'plan_nombre'                   => $this->extractPlanNombre($text),
            'escuela_nombre'                => $this->extractEscuelaNombre($text),
            'total_creditos_obligatorios'   => $obligatorios,
            'creditos_electivos_requeridos' => $electivos,
            'cursos'                        => $this->extractCursos($text),
        ];
    }

    // =========================================================================
    // EXTRACCIÓN DE CABECERA
    // =========================================================================

    private function extractPlanNombre(string $text): string
    {
        if (preg_match('/PLAN\s+DE\s+ESTUDIOS\s+([\w\-]+)/iu', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Plan\s+(?:de\s+Estudios[:\s]+)?([\d\-]+[IVX]*)/i', $text, $m)) {
            return trim($m[1]);
        }
        return 'Plan importado';
    }

    private function extractEscuelaNombre(string $text): string
    {
        if (preg_match('/ESCUELA\s+([^\n\r]+)/iu', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Escuela\s+(?:Profesional\s+de\s+)?([^\n\r]+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return 'Escuela desconocida';
    }

    // =========================================================================
    // EXTRACCIÓN DE CRÉDITOS TOTALES
    // =========================================================================

    /**
     * Extrae los créditos obligatorios y electivos requeridos.
     *
     * Maneja dos formatos:
     *   - Inline:   "Créditos Obligatorios: 213"
     *   - Separado: etiquetas en columna izquierda, números en columna derecha.
     *               smalot las separa: todas las etiquetas primero, luego los números.
     *               Créditos Obligatorios:\nCréditos Electivos:\n...\n213\n15\n0\n0
     *
     * @return array{int, int}  [obligatorios, electivos]
     */
    private function extractResumenCreditos(string $text): array
    {
        $obligatorios = 0;
        $electivos    = 0;

        // ── Formato inline ────────────────────────────────────────────────────
        if (preg_match('/Cr[eé]ditos?\s+Obligatorios?\s*:\s*(\d+)/iu', $text, $m)) {
            $obligatorios = (int) $m[1];
        }
        if (preg_match('/Cr[eé]ditos?\s+Electivos?\s*:\s*(\d+)/iu', $text, $m)) {
            $electivos = (int) $m[1];
        }

        if ($obligatorios || $electivos) {
            return [$obligatorios, $electivos];
        }

        // ── Formato separado ──────────────────────────────────────────────────
        // Etiquetas consecutivas seguidas de los números en el mismo orden.
        if (preg_match(
            '/Cr[eé]ditos?\s+Obligatorios?[^\d\n\r]*[\r\n\s]+'
            . 'Cr[eé]ditos?\s+Electivos?[^\d\n\r]*[\r\n\s]+'
            . '(?:Cr[eé]ditos?\s+de\s+Pr[aá]cticas?[^\d\n\r]*[\r\n\s]+)?'
            . '(?:Otros?\s+Cr[eé]ditos?[^\d\n\r]*[\r\n\s]+)?'
            . '(\d+)\s+(\d+)/isu',
            $text,
            $m
        )) {
            $obligatorios = (int) $m[1];
            $electivos    = (int) $m[2];
        }

        return [$obligatorios, $electivos];
    }

    // =========================================================================
    // EXTRACCIÓN DE CURSOS
    // =========================================================================

    private function extractCursos(string $text): array
    {
        $cursos      = [];
        $cicloActual = 0;
        $lineas      = preg_split('/\r\n|\r|\n/', $text);

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            // ── Detectar encabezado de ciclo ─────────────────────────────────
            // Formato: ICICLO:  IVCICLO:  XCICLO:  etc.
            if (preg_match('/^(X{0,2}(?:IX|IV|V?I{0,3}))CICLO\s*:/i', $linea, $m)) {
                $num = $this->romanToInt(strtoupper(trim($m[1])));
                if ($num > 0) {
                    $cicloActual = $num;
                }
                continue;
            }

            // La línea debe empezar con código: 2 letras + 4 dígitos
            if (!preg_match('/^([A-Z]{2}\d{4})\s/', $linea)) {
                continue;
            }

            $curso = $this->parsearLineaCurso($linea);
            if ($curso === null) continue;

            // Ignorar fila de cabecera de tabla
            if (strtoupper($curso['nombre']) === 'CURSO' || strtoupper($curso['nombre']) === 'NOMBRE') {
                continue;
            }

            $curso['ciclo'] = $cicloActual ?: null;
            $cursos[]       = $curso;
        }

        return $cursos;
    }

    /**
     * Intenta parsear una línea de curso probando dos formatos:
     *
     * Formato A — TIPO en medio (PDF Industrial):
     *   CODE  NOMBRE  [2+ espacios]  TIPO[REQS]  CRED  HT  HP
     *   Ejemplo: "MA1435 CALCULO I    OMA1408    4 48 32"
     *   Ejemplo: "ED1292 ACTIVIDAD DEPORTIVA    O2 0 64"
     *
     * Formato B — TIPO al final (PDF Agroindustrial y otros):
     *   CODE  NOMBRE[con reqs]  CRED  HT  HP  TIPO
     *   Ejemplo: "AG1234 NOMBRE DEL CURSO 3 32 32 O"
     *   Ejemplo: "AG1234 NOMBRE MA1408 4 48 32 O"
     *
     * @return array|null
     */
    private function parsearLineaCurso(string $linea): ?array
    {
        // ── Formato A: TIPO antes de los números ─────────────────────────────
        if (preg_match('/^([A-Z]{2}\d{4})\s+(.+?)\s{2,}([OE])(.*)$/u', $linea, $m)) {
            $despues = $m[4];

            if (preg_match('/^\s*(\d+)\s+(\d+)\s+(\d+)\s*$/', $despues, $nums)) {
                return [
                    'codigo'          => $m[1],
                    'nombre'          => trim($m[2]),
                    'tipo'            => $m[3],
                    'creditos'        => (int) $nums[1],
                    'horas_teoricas'  => (int) $nums[2],
                    'horas_practicas' => (int) $nums[3],
                    'requisitos'      => [],
                ];
            }

            if (preg_match('/^(.*?)\s+(\d+)\s+(\d+)\s+(\d+)\s*$/', $despues, $nums)) {
                preg_match_all('/\b([A-Z]{2}\d{4})\b/', $nums[1], $req);
                return [
                    'codigo'          => $m[1],
                    'nombre'          => trim($m[2]),
                    'tipo'            => $m[3],
                    'creditos'        => (int) $nums[2],
                    'horas_teoricas'  => (int) $nums[3],
                    'horas_practicas' => (int) $nums[4],
                    'requisitos'      => array_unique($req[1] ?? []),
                ];
            }
        }

        // ── Formato B: TIPO al final ──────────────────────────────────────────
        if (preg_match('/^([A-Z]{2}\d{4})\s+(.+?)\s+(\d+)\s+(\d+)\s+(\d+)\s+([OE])\s*$/u', $linea, $m)) {
            [$nombre, $requisitos] = $this->separarNombreRequisitos($m[2]);
            return [
                'codigo'          => $m[1],
                'nombre'          => $nombre,
                'tipo'            => $m[6],
                'creditos'        => (int) $m[3],
                'horas_teoricas'  => (int) $m[4],
                'horas_practicas' => (int) $m[5],
                'requisitos'      => $requisitos,
            ];
        }

        return null;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function romanToInt(string $roman): int
    {
        $map = [
            'X'    => 10,
            'IX'   => 9,
            'VIII' => 8,
            'VII'  => 7,
            'VI'   => 6,
            'V'    => 5,
            'IV'   => 4,
            'III'  => 3,
            'II'   => 2,
            'I'    => 1,
        ];
        return $map[$roman] ?? 0;
    }
}
