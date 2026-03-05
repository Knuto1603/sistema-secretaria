<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class EstudianteTemplateExport implements FromArray, WithHeadings, WithTitle, WithColumnWidths
{
    public function array(): array
    {
        // Filas de ejemplo
        return [
            ['2020100001', 'García López, Juan Carlos', '0', 2020],
            ['2021200002', 'Rodríguez Pérez, María Elena', '1', 2021],
        ];
    }

    public function headings(): array
    {
        return [
            'codigo_universitario',
            'nombre',
            'escuela_codigo',
            'anio_ingreso',
        ];
    }

    public function title(): string
    {
        return 'Estudiantes';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 40,
            'C' => 16,
            'D' => 14,
        ];
    }
}
