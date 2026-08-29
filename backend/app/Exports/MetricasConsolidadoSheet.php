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

class MetricasConsolidadoSheet implements FromCollection, WithHeadings, WithTitle, WithColumnWidths, WithStyles
{
    public function __construct(protected ?string $periodoId = null) {}

    public function collection(): Collection
    {
        if (!$this->periodoId) {
            return collect();
        }

        $solicitudes = Solicitud::whereNotNull('programacion_id')
            ->where('periodo_id', $this->periodoId)
            ->whereHas('tipoSolicitud', fn($q) => $q->whereIn('codigo', ['CUPO_EXT', 'INSC_ESCUELA']))
            ->with('tipoSolicitud:id,codigo')
            ->get(['id', 'programacion_id', 'user_id', 'estado', 'tipo_solicitud_id']);

        if ($solicitudes->isEmpty()) {
            return collect();
        }

        $porSeccion = $solicitudes->groupBy('programacion_id');

        $secciones = ProgramacionAcademica::whereIn('id', $porSeccion->keys())
            ->with(['curso.area', 'docente', 'aulaRelacion', 'grupoHorario', 'escuelaProgramada'])
            ->get()
            ->sortBy([['curso.codigo', 'asc'], ['seccion', 'asc']]);

        return $secciones->map(function ($p) use ($porSeccion) {
            $grupo = $porSeccion->get($p->id);

            $cupoExtra    = $grupo->filter(fn($s) => $s->tipoSolicitud?->codigo === 'CUPO_EXT');
            $inscEscuela  = $grupo->filter(fn($s) => $s->tipoSolicitud?->codigo === 'INSC_ESCUELA');
            $solicitantes = $grupo->pluck('user_id')->unique()->count();

            return [
                $p->curso?->codigo ?? '',
                $p->curso?->nombre ?? '',
                $p->curso?->area?->nombre ?? '',
                $p->escuelaProgramada?->nombre_corto ?? $p->escuelaProgramada?->nombre ?? '',
                $p->grupoHorario?->nombre ?? $p->grupo ?? '',
                $p->seccion ?? '',
                $p->docente?->nombre ?? '',
                $p->aulaRelacion?->nombre ?? '',
                $p->capacidad ?? '',
                $p->n_inscritos ?? 0,
                $solicitantes,
                $cupoExtra->count(),
                $cupoExtra->where('estado', 'aprobada')->count(),
                $cupoExtra->where('estado', 'rechazada')->count(),
                $cupoExtra->where('estado', 'en_revision')->count(),
                $inscEscuela->count(),
                $inscEscuela->where('estado', 'aprobada')->count(),
                $inscEscuela->where('estado', 'rechazada')->count(),
                $inscEscuela->where('estado', 'en_revision')->count(),
            ];
        })->values();
    }

    public function headings(): array
    {
        return [
            'Cód. Curso', 'Nombre del Curso', 'Departamento', 'Escuela Programada',
            'Grupo', 'Sección', 'Docente', 'Aula', 'Capacidad', 'Inscritos',
            'Solicitantes Generales',
            'Solicitudes de cupo Extra',
            'Solicitudes de cupo extra aprobadas',
            'Solicitudes de cupo extra rechazadas',
            'Solicitudes de cupo extra en revisión',
            'Solicitudes de inscripción entre escuelas',
            'Solicitudes de inscripción entre escuela aprobadas',
            'Solicitudes de inscripción en otra escuela rechazadas',
            'Solicitudes de inscripción en otra escuela en revisión',
        ];
    }

    public function title(): string
    {
        return 'Resumen General';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 40, 'C' => 25, 'D' => 25, 'E' => 10, 'F' => 10,
            'G' => 35, 'H' => 18, 'I' => 12, 'J' => 12, 'K' => 16,
            'L' => 16, 'M' => 16, 'N' => 16, 'O' => 16,
            'P' => 18, 'Q' => 18, 'R' => 18, 'S' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
