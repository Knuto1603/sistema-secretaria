<?php

namespace App\Imports;

use Smalot\PdfParser\Parser;

class PlanEstudiosPdfImport
{
    /**
     * Parsea un archivo PDF del SIGA y retorna la estructura del plan de estudios.
     *
     * @param  string  $filePath  Ruta al archivo PDF
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

        return [
            'plan_nombre'                   => $this->extractPlanNombre($text),
            'escuela_nombre'                => $this->extractEscuelaNombre($text),
            'total_creditos_obligatorios'   => $this->extractCreditosObligatorios($text),
            'creditos_electivos_requeridos' => $this->extractCreditosElectivos($text),
            'cursos'                        => $this->extractCursos($text),
        ];
    }

    private function extractPlanNombre(string $text): string
    {
        // Busca patrones como "Plan de Estudios: 2018-1" o "Plan 2018-I"
        if (preg_match('/Plan\s+(?:de\s+Estudios[:\s]+)?(\d{4}[-\s]?\d?[IVX]*)/i', $text, $m)) {
            return trim($m[1]);
        }
        return 'Plan importado';
    }

    private function extractEscuelaNombre(string $text): string
    {
        // Busca líneas que contengan "Escuela" o "Ingeniería"
        if (preg_match('/Escuela\s+(?:Profesional\s+de\s+)?([^\n\r]+)/i', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/Ingeniería\s+[^\n\r]+/i', $text, $m)) {
            return trim($m[0]);
        }
        return 'Escuela desconocida';
    }

    private function extractCreditosObligatorios(string $text): int
    {
        if (preg_match('/Cr[eé]ditos?\s+Obligatorios?[:\s]+(\d+)/i', $text, $m)) {
            return (int) $m[1];
        }
        // Suma total de créditos O en la tabla
        return 0;
    }

    private function extractCreditosElectivos(string $text): int
    {
        if (preg_match('/Cr[eé]ditos?\s+Electivos?[:\s]+(\d+)/i', $text, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    private function extractCursos(string $text): array
    {
        $cursos    = [];
        $cicloActual = 0;

        // Dividir en líneas para procesar
        $lineas = preg_split('/\r\n|\r|\n/', $text);

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            // Detectar ciclo: CICLO I, CICLO II ... CICLO X
            if (preg_match('/^\s*CICLO\s+(I{1,3}V?|VI{0,3}|IX|X|XI{0,2})\s*$/i', $linea, $m)) {
                $cicloActual = $this->romanToInt(strtoupper(trim($m[1])));
                continue;
            }

            // Detectar fila de curso: código(2 letras + 4 dígitos), nombre, horas/créditos, tipo O/E
            // Formato típico SIGA: CB1324 CÁLCULO I 48 32 4 O CB0000
            if (preg_match('/^([A-Z]{2}\d{4})\s+(.+?)\s+(\d+)\s+(\d+)\s+(\d+)\s+([OE])\s*(.*)$/u', $linea, $m)) {
                $requisitos = $this->parseRequisitos(trim($m[7]));
                $cursos[]   = [
                    'ciclo'          => $cicloActual ?: null,
                    'codigo'         => $m[1],
                    'nombre'         => trim($m[2]),
                    'horas_teoricas' => (int) $m[3],
                    'horas_practicas'=> (int) $m[4],
                    'creditos'       => (int) $m[5],
                    'tipo'           => $m[6],
                    'requisitos'     => $requisitos,
                ];
                continue;
            }

            // Formato alternativo: código, nombre, créditos, tipo (sin horas separadas)
            if (preg_match('/^([A-Z]{2}\d{4})\s+(.+?)\s+(\d+)\s+([OE])\s*(.*)$/u', $linea, $m)) {
                $requisitos = $this->parseRequisitos(trim($m[5]));
                $cursos[]   = [
                    'ciclo'          => $cicloActual ?: null,
                    'codigo'         => $m[1],
                    'nombre'         => trim($m[2]),
                    'horas_teoricas' => null,
                    'horas_practicas'=> null,
                    'creditos'       => (int) $m[3],
                    'tipo'           => $m[4],
                    'requisitos'     => $requisitos,
                ];
            }
        }

        return $cursos;
    }

    private function parseRequisitos(string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $partes = preg_split('/[\s\/,]+/', $raw);
        $codigos = [];
        foreach ($partes as $parte) {
            $parte = trim($parte);
            // Solo incluir si parece un código de curso (2 letras + 4 dígitos)
            if (preg_match('/^[A-Z]{2}\d{4}$/', $parte)) {
                $codigos[] = $parte;
            }
        }
        return $codigos;
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
