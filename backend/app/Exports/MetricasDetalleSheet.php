<?php

namespace App\Exports;

use App\Models\Solicitud;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MetricasDetalleSheet implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    public function __construct(protected string $tipo, protected ?string $periodoId = null) {}

    public function collection(): Collection
    {
        return Solicitud::whereHas('tipoSolicitud', fn($q) => $q->where('codigo', $this->tipo))
            ->whereNotNull('programacion_id')
            ->when($this->periodoId, fn($q) => $q->where('periodo_id', $this->periodoId))
            ->with(['user.escuela', 'programacion.curso', 'programacion.escuelaProgramada'])
            ->orderBy('created_at')
            ->get()
            ->map(function ($s) {
                $row = [
                    $s->programacion?->curso?->codigo ?? '',
                    $s->programacion?->curso?->nombre ?? '',
                ];

                if ($this->tipo === 'INSC_ESCUELA') {
                    $row[] = $s->programacion?->escuelaProgramada?->nombre_corto
                          ?? $s->programacion?->escuelaProgramada?->nombre ?? '';
                }

                $row[] = $s->programacion?->grupo ?? '';
                $row[] = $s->programacion?->seccion ?? '';
                $row[] = $s->user?->codigo_universitario ?? '';
                $row[] = $s->user?->name ?? '';
                $row[] = $s->user?->escuela?->nombre_corto ?? $s->user?->escuela?->nombre ?? '';
                $row[] = ucfirst(str_replace('_', ' ', $s->estado));

                if ($this->tipo === 'CUPO_EXT') {
                    $row[] = $s->fuera_de_plan ? 'Sí' : 'No';
                }

                $row[] = $s->created_at->format('d/m/Y H:i');

                return $row;
            });
    }

    public function headings(): array
    {
        $heads = ['Cód. Curso', 'Nombre del Curso'];

        if ($this->tipo === 'INSC_ESCUELA') {
            $heads[] = 'Escuela Programada';
        }

        $heads = array_merge($heads, [
            'Grupo',
            'Sección',
            'Cód. Estudiante',
            'Nombre Estudiante',
            $this->tipo === 'INSC_ESCUELA' ? 'Escuela de Origen' : 'Escuela',
            'Estado',
        ]);

        if ($this->tipo === 'CUPO_EXT') {
            $heads[] = 'Fuera de Plan';
        }

        $heads[] = 'Fecha Solicitud';

        return $heads;
    }

    public function title(): string
    {
        return ($this->tipo === 'INSC_ESCUELA' ? 'INSC_ESCUELA' : 'CUPO_EXT') . ' - DETALLE';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 40, 'C' => 25,
            'D' => 10, 'E' => 10, 'F' => 20,
            'G' => 35, 'H' => 25, 'I' => 18,
            'J' => 14, 'K' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
