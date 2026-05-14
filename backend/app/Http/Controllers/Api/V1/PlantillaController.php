<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlantillaController extends Controller
{
    private const PLANTILLAS = [
        'plantilla-pa-anexo'  => 'Plantilla Con Anexo (departamentos con muchos cursos)',
        'plantilla-pa-inline' => 'Plantilla Inline (departamentos con pocos cursos)',
    ];

    public function index(): JsonResponse
    {
        $plantillas = [];

        foreach (self::PLANTILLAS as $clave => $descripcion) {
            $ruta   = "plantillas/{$clave}.docx";
            $existe = Storage::disk('local')->exists($ruta);

            $plantillas[] = [
                'clave'       => $clave,
                'nombre'      => $descripcion,
                'existe'      => $existe,
                'size'        => $existe ? Storage::disk('local')->size($ruta) : null,
                'updated_at'  => $existe
                    ? date('Y-m-d H:i:s', Storage::disk('local')->lastModified($ruta))
                    : null,
            ];
        }

        return $this->success($plantillas, 'Plantillas de documentos');
    }

    public function descargar(string $clave): BinaryFileResponse
    {
        abort_unless(array_key_exists($clave, self::PLANTILLAS), 404, 'Plantilla no reconocida.');

        $ruta = "plantillas/{$clave}.docx";
        abort_unless(Storage::disk('local')->exists($ruta), 404, 'El archivo de plantilla no existe en el servidor.');

        return response()->download(
            Storage::disk('local')->path($ruta),
            "{$clave}.docx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }

    public function subir(Request $request, string $clave): JsonResponse
    {
        abort_unless(array_key_exists($clave, self::PLANTILLAS), 404, 'Plantilla no reconocida.');

        $request->validate([
            'archivo' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $mime = $value->getMimeType();
                    $ext  = strtolower($value->getClientOriginalExtension());
                    $validMimes = [
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/zip', // algunos clientes envían zip para .docx
                    ];
                    if ($ext !== 'docx' || !in_array($mime, $validMimes)) {
                        $fail('El archivo debe ser un documento Word (.docx).');
                    }
                },
            ],
        ]);

        Storage::disk('local')->putFileAs(
            'plantillas',
            $request->file('archivo'),
            "{$clave}.docx"
        );

        return $this->success(null, 'Plantilla actualizada correctamente.');
    }
}
