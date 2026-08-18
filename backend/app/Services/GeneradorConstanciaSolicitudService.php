<?php

namespace App\Services;

use App\Models\Solicitud;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GeneradorConstanciaSolicitudService
{
    public function generar(Solicitud $solicitud): string
    {
        $config = ConfiguracionInstitucional::getAll();

        $firmaBase64 = null;
        if ($solicitud->firma_digital_path && Storage::disk('public')->exists($solicitud->firma_digital_path)) {
            $firmaBase64 = base64_encode(Storage::disk('public')->get($solicitud->firma_digital_path));
        }

        $pdf = Pdf::loadView('pdf.solicitud-constancia', [
            'solicitud'   => $solicitud,
            'config'      => $config,
            'firmaBase64' => $firmaBase64,
        ])->setPaper('a4');

        $ruta = "constancias/{$solicitud->id}.pdf";
        Storage::disk('public')->put($ruta, $pdf->output());

        return $ruta;
    }
}
