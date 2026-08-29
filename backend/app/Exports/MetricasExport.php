<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MetricasExport implements WithMultipleSheets
{
    public function __construct(protected ?string $periodoId = null) {}

    public function sheets(): array
    {
        return [
            new MetricasConsolidadoSheet($this->periodoId),
            new MetricasResumenSheet('CUPO_EXT', $this->periodoId),
            new MetricasDetalleSheet('CUPO_EXT', $this->periodoId),
            new MetricasResumenSheet('INSC_ESCUELA', $this->periodoId),
            new MetricasDetalleSheet('INSC_ESCUELA', $this->periodoId),
        ];
    }
}
