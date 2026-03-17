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

            // Verificación rápida: la línea debe empezar con código de curso
            if (!preg_match('/^[A-Z]{2}\d{4}\s/', $linea)) {
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
     * Parsea una línea de curso del SIGA-UNP.
     *
     * Las columnas están separadas por TAB (\t):
     *
     *   Sin requisitos (2 partes):
     *     "ED1292 ACTIVIDAD DEPORTIVA\tO2 0 64"
     *     parte[0] = "ED1292 ACTIVIDAD DEPORTIVA"
     *     parte[1] = "O2 0 64"  → tipo=O, cred=2, ht=0, hp=64
     *
     *   Con requisitos (3 partes):
     *     "MA1435 CALCULO I\tOMA1408\t4 48 32"
     *     parte[0] = "MA1435 CALCULO I"
     *     parte[1] = "OMA1408"  → tipo=O, req=[MA1408]
     *     parte[2] = "4 48 32"  → cred=4, ht=48, hp=32
     *
     *   Con múltiples requisitos (3 partes):
     *     "MA2333 ALGEBRA LINEAL\tOMA1435 / MA1470\t3 32 32"
     *
     *   Con requisito especial (3 partes):
     *     "ED3285 TALLER ...\tO100cred./ ED1331\t2 0 64"
     *     "II5392 CREACION ...\tE180cred.\t3 32 32"
     *
     * @return array|null
     */
    private function parsearLineaCurso(string $linea): ?array
    {
        $partes = explode("\t", $linea);

        if (count($partes) < 2) return null;

        // ── Parte 0: "CODIGO NOMBRE" ──────────────────────────────────────────
        if (!preg_match('/^([A-Z]{2}\d{4})\s+(.+)$/u', trim($partes[0]), $m)) {
            return null;
        }
        $codigo = $m[1];
        $nombre = trim($m[2]);

        // ── Parte 1: empieza con TIPO (O|E) ───────────────────────────────────
        $tipoRaw = trim($partes[1]);
        if (!preg_match('/^([OE])(.*)$/u', $tipoRaw, $tm)) {
            return null;
        }
        $tipo    = $tm[1];
        $despues = trim($tm[2]); // texto tras el tipo en la misma columna

        // ── Extraer CRED HT HP y REQUISITOS ───────────────────────────────────
        if (count($partes) >= 3) {
            // Tercera columna tiene los números: "4 48 32"
            $numStr = trim($partes[2]);
            if (!preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/', $numStr, $nm)) {
                return null;
            }
            $creditos   = (int) $nm[1];
            $hteorica   = (int) $nm[2];
            $hpractica  = (int) $nm[3];
            // $despues contiene los requisitos ("MA1408", "MA1435 / MA1470", "100cred./ ED1331", etc.)
            preg_match_all('/\b([A-Z]{2}\d{4})\b/', $despues, $req);
            $requisitos = array_unique($req[1] ?? []);
        } else {
            // Sin tercera columna: $despues tiene cred ht hp pegados al tipo ("2 0 64")
            if (!preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/', $despues, $nm)) {
                return null;
            }
            $creditos   = (int) $nm[1];
            $hteorica   = (int) $nm[2];
            $hpractica  = (int) $nm[3];
            $requisitos = [];
        }

        return [
            'codigo'          => $codigo,
            'nombre'          => $nombre,
            'tipo'            => $tipo,
            'creditos'        => $creditos,
            'horas_teoricas'  => $hteorica,
            'horas_practicas' => $hpractica,
            'requisitos'      => $requisitos,
        ];
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
