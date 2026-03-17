<?php

namespace App\Imports;

use Smalot\PdfParser\Parser;

/**
 * Parser de PDFs de Plan de Estudios del SIGA-UNP.
 *
 * Formato de columnas por fila de curso:
 *   CODIGO | NOMBRE [REQUISITOS] | CRED | HT | HP | TIPO
 *
 * Formato de ciclo:  "CICLO: I", "CICLO: II", ...
 *
 * Resumen al final (puede estar en columnas separadas):
 *   Créditos Obligatorios: 220    Créditos de Prácticas: 0
 *   Créditos Electivos:     15    Otros Créditos:         0
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
        // "PLAN DE ESTUDIOS 2018-1"  o  "Plan de Estudios: 2018-1"
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
     * Maneja dos formatos:
     *   - Inline:   "Créditos Obligatorios: 220"
     *   - Separado: etiquetas en una columna, números en otra (smalot las separa)
     *
     * @return array{int, int}  [obligatorios, electivos]
     */
    private function extractResumenCreditos(string $text): array
    {
        $obligatorios = 0;
        $electivos    = 0;

        // ── Formato inline (número justo después de ":")  ────────────────────
        if (preg_match('/Cr[eé]ditos?\s+Obligatorios?\s*:\s*(\d+)/iu', $text, $m)) {
            $obligatorios = (int) $m[1];
        }
        if (preg_match('/Cr[eé]ditos?\s+Electivos?\s*:\s*(\d+)/iu', $text, $m)) {
            $electivos = (int) $m[1];
        }

        if ($obligatorios || $electivos) {
            return [$obligatorios, $electivos];
        }

        // ── Formato separado: etiquetas primero, luego números  ──────────────
        // El PDF tiene 4 etiquetas consecutivas y luego 4 números.
        // Ejemplo extraído por smalot:
        //   Créditos Obligatorios:\nCréditos Electivos:\nCréditos de Prácticas:\nOtros Créditos:\n220\n15\n0\n0
        if (preg_match(
            '/Cr[eé]ditos?\s+Obligatorios?[^\d\n\r]*[\r\n\s]+'
            . 'Cr[eé]ditos?\s+Electivos?[^\d\n\r]*[\r\n\s]+'
            . '(?:Cr[eé]ditos?\s+de\s+Pr[aá]cticas?[^\d\n\r]*[\r\n\s]+)?'
            . '(?:Otros?\s+Cr[eé]ditos?[^\d\n\r]*[\r\n\s]+)?'
            . '(\d+)\D+(\d+)/isu',
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

            // ── Detectar cambio de ciclo  ─────────────────────────────────────
            // Soporta: "CICLO: I", "CICLO: II", "CICLO I" (sin dos puntos)
            if (preg_match('/^CICLO\s*:?\s*(X{0,2}(?:IX|IV|V?I{0,3}))\s*$/i', $linea, $m)) {
                $num = $this->romanToInt(strtoupper(trim($m[1])));
                if ($num > 0) {
                    $cicloActual = $num;
                }
                continue;
            }

            // ── Detectar fila de curso  ───────────────────────────────────────
            // Formato: CODIGO  NOMBRE [REQUISITOS]  CRED  HT  HP  TIPO
            // El código siempre empieza con 2 letras mayúsculas + 4 dígitos.
            // Al final hay exactamente 3 números y una letra O/E.
            if (!preg_match(
                '/^([A-Z]{2}\d{4})\s+(.+?)\s+(\d+)\s+(\d+)\s+(\d+)\s+([OE])\s*$/u',
                $linea,
                $m
            )) {
                continue;
            }

            [$nombre, $requisitos] = $this->separarNombreRequisitos($m[2]);

            // Ignorar la fila de cabecera de tabla si llegara a coincidir
            if (strtoupper($nombre) === 'CURSO' || strtoupper($nombre) === 'NOMBRE') {
                continue;
            }

            $cursos[] = [
                'ciclo'           => $cicloActual ?: null,
                'codigo'          => $m[1],
                'nombre'          => $nombre,
                'creditos'        => (int) $m[3],
                'horas_teoricas'  => (int) $m[4],
                'horas_practicas' => (int) $m[5],
                'tipo'            => $m[6],       // 'O' | 'E'
                'requisitos'      => $requisitos, // array de códigos
            ];
        }

        return $cursos;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Separa el nombre del curso de sus prerequisitos.
     *
     * El texto recibido tiene la forma:
     *   "CALCULO I"                            → nombre="CALCULO I",      reqs=[]
     *   "CALCULO I MA1408"                     → nombre="CALCULO I",      reqs=["MA1408"]
     *   "PROGRAMACION I SI1216 / SI1447"       → nombre="PROGRAMACION I", reqs=["SI1216","SI1447"]
     *   "TALLER DE REDACCION 100cred./ ED1331" → nombre="TALLER DE REDACCION", reqs=["ED1331"]
     *
     * @return array{string, string[]}
     */
    private function separarNombreRequisitos(string $texto): array
    {
        // Buscar el primer código de curso (XX\d{4}) en el texto
        if (!preg_match('/\b([A-Z]{2}\d{4})\b/', $texto, $_, PREG_OFFSET_CAPTURE)) {
            return [trim($texto), []];
        }

        $pos    = $_[0][1];
        $nombre = substr($texto, 0, $pos);

        // Limpiar notaciones especiales al final del nombre (ej. "100cred./")
        $nombre = preg_replace('/\s+\d+\s*cred\.?\s*\/?.*$/iu', '', $nombre);
        $nombre = trim($nombre, " \t/.-");

        // Extraer todos los códigos de curso que aparecen como prerequisitos
        $resto = substr($texto, $pos);
        preg_match_all('/\b([A-Z]{2}\d{4})\b/', $resto, $matches);
        $requisitos = $matches[1] ?? [];

        return [$nombre, array_unique($requisitos)];
    }

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
