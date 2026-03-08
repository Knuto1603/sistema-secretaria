<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;

class PlanEstudiosTemplateExport implements FromArray, WithHeadings, WithTitle, WithColumnWidths
{
    public function array(): array
    {
        return [
            ['ED1292', 'ACTIVIDAD DEPORTIVA',  '1', '2', 'O', ''],
            ['MA1408', 'MATEMATICA BASICA',    '1', '4', 'O', ''],
            ['SI1447', 'ALGORITMOS',           '1', '4', 'O', ''],
            ['MA1409', 'CALCULO I',            '2', '4', 'O', 'MA1408'],
            ['MA1410', 'CALCULO II',           '3', '4', 'O', 'MA1409'],
            ['II4366', 'ENERGIAS RENOVABLES',  '7', '3', 'E', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'codigo_curso',
            'nombre_curso',
            'ciclo',
            'creditos',
            'tipo',             // O = Obligatorio  |  E = Electivo
            'codigo_requisito', // Opcional. Si son varios: separar con coma (MA1408,FIS101)
        ];
    }

    public function title(): string
    {
        return 'Plan de Estudios';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 50,
            'C' => 10,
            'D' => 12,
            'E' => 10,
            'F' => 35,
        ];
    }
}
