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
            ['ED1292', 'ACTIVIDAD DEPORTIVA',  '1', '2', 'O', '0',  '64', '64',  '0', '4', '4', ''],
            ['MA1408', 'MATEMATICA BASICA',    '1', '4', 'O', '48', '32', '80',  '3', '2', '5', ''],
            ['SI1447', 'ALGORITMOS',           '1', '4', 'O', '48', '32', '80',  '3', '2', '5', ''],
            ['MA1409', 'CALCULO I',            '2', '4', 'O', '48', '32', '80',  '3', '2', '5', 'MA1408'],
            ['MA1410', 'CALCULO II',           '3', '4', 'O', '48', '32', '80',  '3', '2', '5', 'MA1409'],
            ['II4366', 'ENERGIAS RENOVABLES',  '7', '3', 'E', '32', '32', '64',  '2', '2', '4', ''],
        ];
    }

    public function headings(): array
    {
        return [
            'codigo_curso',
            'nombre_curso',
            'ciclo',
            'creditos',
            'tipo',                  // O = Obligatorio  |  E = Electivo
            'ht_semestral',
            'hp_semestral',
            'total_horas_semestral',
            'ht_semanal',            // Horas teóricas por semana (se guarda como horas_teoricas)
            'hp_semanal',            // Horas prácticas por semana (se guarda como horas_practicas)
            'total_horas_semanal',
            'requisitos',            // Opcional. Si son varios: separar con coma (MA1408, FIS101)
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
            'F' => 14,
            'G' => 14,
            'H' => 18,
            'I' => 12,
            'J' => 12,
            'K' => 16,
            'L' => 35,
        ];
    }
}
