<?php

namespace App\Imports;

use DOMDocument;
use DOMXPath;

/**
 * Parser de un archivo HTML de historial académico del SIGA.
 *
 * Formato: windows-1252, una TABLE por página, filas tipo:
 *  - SEMESTRE <valor>   → inicia bloque de semestre
 *  - <CODIGO> <NOMBRE>  → fila de curso (código + nombre)
 *  - <CREDITOS> <NOTA>  → fila de detalle del mismo curso (créditos + nota/C)
 */
class HistorialHtmlImport
{
    private string $codigo = '';
    private string $nombre = '';
    private array  $cursos = [];

    public function parse(string $htmlContent): void
    {
        if (!mb_check_encoding($htmlContent, 'UTF-8')) {
            $htmlContent = mb_convert_encoding($htmlContent, 'UTF-8', 'Windows-1252');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlContent);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $this->extractHeader($xpath);

        // Siempre extraer cursos aunque el código no se haya encontrado en el header
        // (el código puede venir del nombre del archivo como fallback externo)
        $this->extractCursos($xpath);
    }

    private function extractHeader(DOMXPath $xpath): void
    {
        // Patrón: 10 dígitos - nombre (acepta guion normal o em-dash)
        $pattern = '/^(\d{10})\s*[-–]\s*(.+)$/u';

        // 1. Intentar múltiples variantes del selector FONT (distintos formatos de color del SIGA)
        $queries = [
            '//font[@size="3" and @color="000080"]',
            '//font[@size="3" and @color="#000080"]',
            '//font[@size="3" and @color="0000FF"]',
            '//font[@size="3" and @color="#0000FF"]',
            '//font[@size="3"]',
            '//font[@color="000080"]',
            '//font[@color="#000080"]',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (preg_match($pattern, $text, $m)) {
                    $this->codigo = $m[1];
                    $this->nombre = mb_convert_case(trim($m[2]), MB_CASE_TITLE, 'UTF-8');
                    return;
                }
            }
        }

        // 2. Buscar el patrón en cualquier nodo de texto del documento
        $textNodes = $xpath->query('//text()');
        foreach ($textNodes as $textNode) {
            $text = trim($textNode->nodeValue ?? '');
            if (preg_match($pattern, $text, $m)) {
                $this->codigo = $m[1];
                $this->nombre = mb_convert_case(trim($m[2]), MB_CASE_TITLE, 'UTF-8');
                return;
            }
        }

        // 3. Búsqueda en texto completo del body (por si hay espacios/saltos de línea entre código y nombre)
        $body = $xpath->query('//body');
        if ($body->length > 0) {
            $fullText = preg_replace('/\s+/', ' ', $body->item(0)->textContent ?? '');
            if (preg_match('/(\d{10})\s*[-–]\s*([A-ZÁÉÍÓÚÑ][^0-9]{5,80})/u', $fullText, $m)) {
                $this->codigo = $m[1];
                $this->nombre = mb_convert_case(trim($m[2]), MB_CASE_TITLE, 'UTF-8');
            }
        }
    }

    private function extractCursos(DOMXPath $xpath): void
    {
        $currentSemestre = null;
        $pendingCodigo   = null;
        $pendingNombre   = null;

        $rows = $xpath->query('//table//tr');

        foreach ($rows as $tr) {
            $text = preg_replace('/\s+/', ' ', trim($tr->textContent));

            if ($text === '') {
                continue;
            }

            // --- Detectar fila de SEMESTRE ---
            if (preg_match('/^SEMESTRE\s+(\S+)/', $text, $m)) {
                $semVal = $m[1];

                if (str_contains($semVal, '-') && str_replace('-', '', $semVal) === '') {
                    // Solo guiones: "-----" = convalidaciones sin semestre
                    $currentSemestre = null;
                } elseif (preg_match('/^(\d{4})([012])$/', $semVal, $m2)) {
                    // Formato antiguo SIGA: "19971" → "1997-1"
                    $currentSemestre = $m2[1] . '-' . $m2[2];
                } else {
                    // Formato moderno: "2026-0" se guarda tal cual
                    $currentSemestre = $semVal;
                }

                $pendingCodigo = null;
                $pendingNombre = null;
                continue;
            }

            // --- Detectar fila de código + nombre de curso ---
            // Patrón: empieza con 2 letras mayúsculas + 4 dígitos
            if (preg_match('/^([A-Z]{2}\d{4})\s+(.+)$/', $text, $m)) {
                $pendingCodigo = $m[1];
                $pendingNombre = trim($m[2]);
                continue;
            }

            // --- Detectar fila de créditos + nota (sólo si hay curso pendiente) ---
            if ($pendingCodigo !== null) {
                $parts = array_values(array_filter(explode(' ', $text)));

                // La fila de créditos/nota empieza con un número entero (créditos)
                if (count($parts) >= 1 && ctype_digit($parts[0])) {
                    $creditos = (int) $parts[0];
                    $noteStr  = $parts[1] ?? null;

                    $nota = null;
                    $tipo = null;

                    if ($noteStr === 'C') {
                        $tipo = 'C'; // Convalidado
                    } elseif ($noteStr !== null && is_numeric($noteStr)) {
                        $nota = (float) $noteStr;
                    }

                    $this->cursos[] = [
                        'codigo'   => $pendingCodigo,
                        'nombre'   => $pendingNombre,
                        'semestre' => $currentSemestre,
                        'creditos' => $creditos,
                        'nota'     => $nota,
                        'tipo'     => $tipo,
                    ];

                    $pendingCodigo = null;
                    $pendingNombre = null;
                }
            }
        }
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getCursos(): array
    {
        return $this->cursos;
    }
}
