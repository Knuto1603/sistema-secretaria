<?php

namespace App\Exports;

use App\Models\ProgramacionAcademica;
use App\Models\Solicitud;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MetricasResumenSheet implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    public function __construct(protected string $tipo) {}

    public function collection(): Collection
    {
        // IDs de programaciones con solicitudes del tipo indicado, con conteo total
        $conteos = Solicitud::whereHas('tipoSolicitud', fn($q) => $q->where('codigo', $this->tipo))
            ->whereNotNull('programacion_id')
            ->selectRaw('programacion_id, count(*) as total')
            ->groupBy('programacion_id')
            ->pluck('total', 'programacion_id');

        if ($conteos->isEmpty()) {
            return collect();
        }

        // Conteo de solicitudes pendientes por programación
        $conteosPendientes = Solicitud::whereHas('tipoSolicitud', fn($q) => $q->where('codigo', $this->tipo))
            ->whereNotNull('programacion_id')
            ->where('estado', 'pendiente')
            ->selectRaw('programacion_id, count(*) as total')
            ->groupBy('programacion_id')
            ->pluck('total', 'programacion_id');

        // Cursos que tienen alguna solicitud de este tipo
        $cursoIds = ProgramacionAcademica::whereIn('id', $conteos->keys())
            ->pluck('curso_id')
            ->unique();

        // Todas las secciones de esos cursos en el periodo activo
        $secciones = ProgramacionAcademica::whereIn('curso_id', $cursoIds)
            ->whereHas('periodo', fn($q) => $q->where('activo', true))
            ->with(['curso', 'docente', 'aulaRelacion', 'escuelaProgramada'])
            ->orderBy('curso_id')
            ->orderBy('grupo')
            ->get();

        return $secciones->map(function ($p) use ($conteos, $conteosPendientes) {
            $row = [
                $p->curso?->codigo ?? '',
                $p->curso?->nombre ?? '',
                $p->escuelaProgramada?->nombre_corto ?? $p->escuelaProgramada?->nombre ?? '',
                $p->grupo ?? '',
                $p->seccion ?? '',
                $p->docente?->nombre ?? '',
                $p->aulaRelacion?->nombre ?? '',
                $p->capacidad ?? '',
                $p->n_inscritos ?? 0,
                (int) ($conteos->get($p->id) ?? 0),
                (int) ($conteosPendientes->get($p->id) ?? 0),
            ];

            return $row;
        });
    }

    public function headings(): array
    {
        return [
            'Cód. Curso',
            'Nombre del Curso',
            'Escuela Programada',
            'Grupo',
            'Sección',
            'Docente',
            'Aula',
            'Capacidad',
            'Inscritos',
            'Solicitantes',
            'Solicitantes Pendientes',
        ];
    }

    public function title(): string
    {
        return $this->tipo === 'INSC_ESCUELA' ? 'INSC_ESCUELA' : 'CUPO_EXT';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 40, 'C' => 25,
            'D' => 10, 'E' => 10, 'F' => 35,
            'G' => 18, 'H' => 12, 'I' => 12,
            'J' => 14, 'K' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
