<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolicitudesExport implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    public function __construct(protected Collection $solicitudes) {}

    public function collection(): Collection
    {
        return $this->solicitudes->map(fn($s) => [
            $s->created_at->format('d/m/Y H:i'),
            $s->user?->codigo_universitario ?? '',
            $s->user?->name ?? '',
            $s->user?->escuela?->nombre_corto ?? $s->user?->escuela?->nombre ?? '',
            $s->programacion?->curso?->codigo ?? '',
            $s->programacion?->curso?->nombre ?? '',
            $s->programacion?->escuelaProgramada?->nombre_corto ?? $s->programacion?->escuelaProgramada?->nombre ?? '',
            $s->programacion?->seccion ?? '',
            $s->programacion?->grupo ?? '',
            $s->programacion?->aulaRelacion?->nombre ?? $s->programacion?->aula ?? '',
        ]);
    }

    public function headings(): array
    {
        return [
            'Fecha/Hora',
            'Cód. Universitario',
            'Nombre Completo',
            'Escuela Alumno',
            'Cód. Curso',
            'Nombre Curso',
            'Escuela Programada',
            'Sección',
            'Grupo',
            'Aula',
        ];
    }

    public function title(): string
    {
        return 'Solicitudes';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 20,
            'C' => 35,
            'D' => 25,
            'E' => 14,
            'F' => 40,
            'G' => 25,
            'H' => 10,
            'I' => 10,
            'J' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
