<?php

namespace App\Imports;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Spatie\Permission\Models\Role;

class AlumnosHtmlImport
{
    private array $resultados  = [];
    private ?Role $rolEstudiante = null;

    public function import(string $filePath): void
    {
        $this->rolEstudiante = Role::where('name', 'estudiante')->where('guard_name', 'web')->first();

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

        if (count($dataValues) < 2) {
            return;
        }

        $codigo = $dataValues[0];

        // El código universitario es exactamente 10 dígitos numéricos
        if (!preg_match('/^\d{10}$/', $codigo)) {
            return;
        }

        // El segundo valor puede ser el DNI (7-8 dígitos) o directamente el nombre
        if (preg_match('/^\d{7,8}$/', $dataValues[1])) {
            $nombre = $dataValues[2] ?? null;
        } else {
            $nombre = $dataValues[1];
        }

        if (!$nombre) {
            return;
        }

        try {
            $user = User::updateOrCreate(
                ['codigo_universitario' => $codigo],
                [
                    'name'         => $this->formatearNombre($nombre),
                    'tipo_usuario' => 'estudiante',
                    'email'        => User::generarEmailEstudiante($codigo),
                    'activo'       => true,
                ]
            );

            // Deriva escuela_id y anio_ingreso del código universitario
            $user->asignarDatosDesdeCodigoUniversitario();

            if ($this->rolEstudiante) {
                $user->assignRole($this->rolEstudiante);
            }

            $this->resultados[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'estado' => 'importado',
            ];
        } catch (\Exception $e) {
            $this->resultados[] = [
                'codigo'  => $codigo,
                'nombre'  => $nombre,
                'estado'  => 'error',
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * Convierte "APELLIDO APELLIDO-NOMBRE NOMBRE" a "Apellido Apellido-Nombre Nombre"
     */
    private function formatearNombre(string $nombre): string
    {
        return mb_convert_case(trim($nombre), MB_CASE_TITLE, 'UTF-8');
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
