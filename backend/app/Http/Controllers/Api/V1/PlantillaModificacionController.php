<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlantillaModificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlantillaModificacionController extends Controller
{
    private const TIPOS_VALIDOS = ['cierre', 'cierre_apertura', 'fusion', 'cambio_aula'];

    private const LABELS = [
        'cierre'         => 'Cierre de cursos',
        'cierre_apertura'=> 'Cierre y apertura de cursos',
        'fusion'         => 'Fusión de secciones',
        'cambio_aula'    => 'Cambio de aula / grupo',
    ];

    /**
     * GET /plantillas-modificacion
     * Estado de las 4 plantillas (existe o no).
     */
    public function index(): JsonResponse
    {
        $plantillas = PlantillaModificacion::whereIn('tipo', self::TIPOS_VALIDOS)->get()->keyBy('tipo');

        $estado = collect(self::TIPOS_VALIDOS)->map(function ($tipo) use ($plantillas) {
            $p = $plantillas->get($tipo);
            $existe = $p && Storage::disk('local')->exists($p->ruta);

            return [
                'tipo'           => $tipo,
                'label'          => self::LABELS[$tipo],
                'cargada'        => $existe,
                'nombre_archivo' => $existe ? $p->nombre_archivo : null,
                'actualizado_at' => $existe ? $p->updated_at?->toIso8601String() : null,
            ];
        });

        return $this->success($estado, 'Estado de plantillas de modificación');
    }

    /**
     * POST /plantillas-modificacion/{tipo}
     * Sube o reemplaza la plantilla para un tipo.
     */
    public function subir(Request $request, string $tipo): JsonResponse
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS)) {
            return $this->error("Tipo de plantilla inválido. Válidos: " . implode(', ', self::TIPOS_VALIDOS));
        }

        $request->validate([
            'plantilla' => ['required', 'file', 'mimes:docx', 'max:10240'],
        ], [
            'plantilla.required' => 'El archivo de plantilla es obligatorio.',
            'plantilla.mimes'    => 'La plantilla debe ser un archivo .docx (Word).',
            'plantilla.max'      => 'La plantilla no puede superar 10 MB.',
        ]);

        $existente = PlantillaModificacion::where('tipo', $tipo)->first();

        // Eliminar archivo anterior si existe
        if ($existente && Storage::disk('local')->exists($existente->ruta)) {
            Storage::disk('local')->delete($existente->ruta);
        }

        $archivo         = $request->file('plantilla');
        $nombreOriginal  = $archivo->getClientOriginalName();
        $nombreSafe      = "plantilla-modificacion-{$tipo}-" . Str::random(8) . ".docx";
        $ruta            = "plantillas/modificacion/{$nombreSafe}";

        Storage::disk('local')->put($ruta, file_get_contents($archivo->getRealPath()));

        PlantillaModificacion::updateOrCreate(
            ['tipo' => $tipo],
            [
                'nombre_archivo' => $nombreOriginal,
                'ruta'           => $ruta,
                'subido_por'     => $request->user()->id,
            ]
        );

        return $this->success([
            'tipo'           => $tipo,
            'label'          => self::LABELS[$tipo],
            'nombre_archivo' => $nombreOriginal,
        ], "Plantilla '" . self::LABELS[$tipo] . "' cargada correctamente.");
    }

    /**
     * DELETE /plantillas-modificacion/{tipo}
     * Elimina una plantilla.
     */
    public function eliminar(string $tipo): JsonResponse
    {
        if (!in_array($tipo, self::TIPOS_VALIDOS)) {
            return $this->error("Tipo de plantilla inválido.");
        }

        $plantilla = PlantillaModificacion::where('tipo', $tipo)->first();

        if (!$plantilla) {
            return $this->notFound('No hay plantilla cargada para ese tipo.');
        }

        if (Storage::disk('local')->exists($plantilla->ruta)) {
            Storage::disk('local')->delete($plantilla->ruta);
        }

        $plantilla->delete();

        return $this->success(null, 'Plantilla eliminada correctamente.');
    }

    /**
     * GET /plantillas-modificacion/{tipo}/descargar
     * Descarga la plantilla actual para editarla.
     */
    public function descargar(string $tipo): BinaryFileResponse
    {
        $plantilla = PlantillaModificacion::where('tipo', $tipo)->first();

        abort_if(!$plantilla || !Storage::disk('local')->exists($plantilla->ruta), 404, 'Plantilla no encontrada.');

        return response()->download(
            Storage::disk('local')->path($plantilla->ruta),
            $plantilla->nombre_archivo,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }
}
