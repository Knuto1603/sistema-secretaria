<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MetricasExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new MetricasResumenSheet('CUPO_EXT'),
            new MetricasDetalleSheet('CUPO_EXT'),
            new MetricasResumenSheet('INSC_ESCUELA'),
            new MetricasDetalleSheet('INSC_ESCUELA'),
        ];
    }
}
