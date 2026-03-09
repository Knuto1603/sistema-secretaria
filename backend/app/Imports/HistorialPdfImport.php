<?php

namespace App\Imports;

use App\Models\Curso;
use App\Models\HistorialAcademico;
use App\Models\User;
use Smalot\PdfParser\Parser;

class HistorialPdfImport
{
    private string  $codigoEncontrado = '';
    private int     $importados       = 0;
    private int     $omitidos         = 0; // cursos reprobados (nota <= 10)
    private array   $errores          = [];

    /**
     * @throws \Exception si el PDF no corresponde al usuario autenticado
     */
    public function import(string $filePath, User $user): void
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();

        // Extraer código de alumno del PDF
        if (preg_match('/ALUMNO:\s+(\d{10})/', $text, $m)) {
            $this->codigoEncontrado = $m[1];
        }

        // Verificar que el PDF pertenece al usuario autenticado
        if ($this->codigoEncontrado && $user->codigo_universitario !== $this->codigoEncontrado) {
            throw new \Exception(
                "El PDF pertenece al alumno {$this->codigoEncontrado}, no a tu cuenta."
            );
        }

        // Limpiar historial anterior importado del PDF (mantener autoreportes)
        HistorialAcademico::where('user_id', $user->id)
            ->where('fuente', 'importado')
            ->delete();

        // Parsear semestres y cursos
        $this->parsearCursos($text, $user->id);

        // Actualizar fecha de última actualización
        $user->update(['ultima_actualizacion_historial' => now()]);
    }

    private function parsearCursos(string $text, string $userId): void
    {
        // Dividir texto en líneas y limpiar
        $lines = array_map('trim', explode("\n", $text));

        $semestreActual = null;

        // Patrón de código de curso: 2 letras + 4 dígitos (ej: SI3422, MA2441, ED1292)
        $patronCurso = '/^([A-Z]{2}\d{4})\s+(.+?)\s+(O|E)\s+(\d+)\s+([\d.]+)\s*$/';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Detectar inicio de semestre: "Semestre: 2021-1" o "Semestre: 2024-0"
            if (preg_match('/^Semestre:\s+([\d]+-[\d]+)$/', $line, $m)) {
                $semestreActual = $m[1];
                continue;
            }

            // Detectar fila de curso
            if ($semestreActual && preg_match($patronCurso, $line, $m)) {
                [, $codigo, $nombre, $tipo, $creditos, $notaStr] = $m;
                $nota = (float) $notaStr;

                // Solo importar cursos aprobados (nota > 10)
                if ($nota <= 10) {
                    $this->omitidos++;
                    continue;
                }

                $this->guardarCurso(
                    $userId,
                    trim($codigo),
                    trim($nombre),
                    $tipo,
                    (int) $creditos,
                    $nota,
                    $semestreActual
                );
            }
        }
    }

    private function guardarCurso(
        string $userId,
        string $codigo,
        string $nombre,
        string $tipo,
        int    $creditos,
        float  $nota,
        string $semestre
    ): void {
        try {
            // Buscar curso por código, crearlo si no existe
            $curso = Curso::firstOrCreate(
                ['codigo' => strtoupper($codigo)],
                ['nombre' => mb_convert_case(trim($nombre), MB_CASE_TITLE, 'UTF-8')]
            );

            HistorialAcademico::updateOrCreate(
                [
                    'user_id'   => $userId,
                    'curso_id'  => $curso->id,
                    'semestre'  => $semestre,
                ],
                [
                    'fuente'   => 'importado',
                    'tipo'     => $tipo,
                    'creditos' => $creditos,
                    'nota'     => $nota,
                ]
            );

            $this->importados++;
        } catch (\Exception $e) {
            $this->errores[] = "{$codigo} ({$semestre}): " . $e->getMessage();
        }
    }

    public function getResumen(): array
    {
        return [
            'codigo_alumno' => $this->codigoEncontrado,
            'importados'    => $this->importados,
            'omitidos'      => $this->omitidos,
            'errores'       => count($this->errores),
            'detalle_errores' => $this->errores,
        ];
    }
}
