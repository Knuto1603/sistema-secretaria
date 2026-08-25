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
    public function __construct(protected string $tipo, protected ?string $periodoId = null) {}

    public function collection(): Collection
    {
        // IDs de programaciones con solicitudes del tipo indicado, con conteo, del periodo seleccionado
        $conteos = Solicitud::whereHas('tipoSolicitud', fn($q) => $q->where('codigo', $this->tipo))
            ->whereNotNull('programacion_id')
            ->when($this->periodoId, fn($q) => $q->where('periodo_id', $this->periodoId))
            ->selectRaw('programacion_id, count(*) as total')
            ->groupBy('programacion_id')
            ->pluck('total', 'programacion_id');

        if ($conteos->isEmpty() || !$this->periodoId) {
            return collect();
        }

        // Cursos que tienen alguna solicitud de este tipo
        $cursoIds = ProgramacionAcademica::whereIn('id', $conteos->keys())
            ->pluck('curso_id')
            ->unique();

        // Todas las secciones de esos cursos en el periodo seleccionado
        $secciones = ProgramacionAcademica::periodo($this->periodoId)
            ->select('programacion_secciones.*')
            ->whereIn('programacion_secciones.curso_id', $cursoIds)
            ->with(['curso.area', 'docente', 'aulaRelacion', 'grupoHorario', 'escuelaProgramada'])
            ->orderBy('programacion_secciones.curso_id')
            ->orderBy('programacion_secciones.grupo')
            ->get();

        return $secciones->map(function ($p) use ($conteos) {
            $row = [
                $p->curso?->codigo ?? '',
                $p->curso?->nombre ?? '',
                $p->curso?->area?->nombre ?? '',
                $p->escuelaProgramada?->nombre_corto ?? $p->escuelaProgramada?->nombre ?? '',
            ];

            $row[] = $p->grupoHorario?->nombre ?? $p->grupo ?? '';
            $row[] = $p->seccion ?? '';
            $row[] = $p->docente?->nombre ?? '';
            $row[] = $p->aulaRelacion?->nombre ?? '';
            $row[] = $p->capacidad ?? '';
            $row[] = $p->n_inscritos ?? 0;
            $row[] = (int) ($conteos->get($p->id) ?? 0);

            return $row;
        });
    }

    public function headings(): array
    {
        $heads = ['Cód. Curso', 'Nombre del Curso', 'Departamento', 'Escuela Programada'];

        return array_merge($heads, [
            'Grupo',
            'Sección',
            'Docente',
            'Aula',
            'Capacidad',
            'Inscritos',
            'Solicitantes',
        ]);
    }

    public function title(): string
    {
        return $this->tipo === 'INSC_ESCUELA' ? 'INSC_ESCUELA' : 'CUPO_EXT';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 40, 'C' => 25,
            'D' => 25, 'E' => 10, 'F' => 10,
            'G' => 35, 'H' => 18, 'I' => 12, 'J' => 12, 'K' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
