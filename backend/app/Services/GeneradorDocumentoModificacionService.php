<?php

namespace App\Services;

use App\Models\Area;
use App\Models\ConfiguracionInstitucional;
use App\Models\DocumentoModificacionArea;
use App\Models\GeneracionModificacion;
use App\Models\ModificacionProgramacion;
use App\Models\PlantillaModificacion;
use App\Models\User;
use App\Repositories\Contracts\ModificacionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;
use ZipArchive;

class GeneradorDocumentoModificacionService
{
    // Tipos de modificación que pertenecen a cada plantilla
    private const TIPOS_CIERRE   = ['cerrar_curso'];
    private const TIPOS_APERTURA = ['abrir_seccion'];
    private const TIPOS_FUSION   = ['unificacion_secciones'];
    private const TIPOS_AULA     = ['cambio_aula', 'cambio_grupo', 'cambio_aula_y_grupo'];

    public function __construct(
        protected ModificacionRepositoryInterface $modificacionRepository
    ) {}

    /**
     * Devuelve todas las modificaciones pendientes del periodo agrupadas por área y tipo,
     * listas para selección con checkboxes en el frontend.
     */
    public function preview(string $periodoId): array
    {
        $modificaciones = $this->modificacionRepository->getPendientesPorPeriodo($periodoId);

        if ($modificaciones->isEmpty()) {
            return [];
        }

        $grupos    = $this->agruparPorAreaYTipoDoc($modificaciones);
        $resultado = [];

        foreach ($grupos as $areaId => $tiposDoc) {
            $primero = $modificaciones->first(fn ($m) => $m->programacion?->curso?->area_id === $areaId);
            $area    = $primero?->programacion?->curso?->area;

            foreach ($tiposDoc as $tipoDoc => $mods) {
                $resultado[] = [
                    'area_id'             => $areaId,
                    'area_nombre'         => $area?->nombre_tabla ?? $area?->nombre ?? '—',
                    'tipo_documento'      => $tipoDoc,
                    'tipo_label'          => $this->labelTipoDoc($tipoDoc),
                    'total_modificaciones'=> $mods->count(),
                    'plantilla_existe'    => $this->plantillaExiste($tipoDoc),
                    'modificaciones'      => $mods->map(fn ($m) => $this->resumenModificacion($m))->values()->all(),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Genera los documentos Word para los IDs seleccionados, crea la generación y devuelve el modelo.
     */
    public function generar(
        string $periodoId,
        array  $modificacionIds,
        string $numeroOficio,
        User   $generadoPor
    ): GeneracionModificacion {
        $modificaciones = $this->modificacionRepository->getPendientesPorIds($modificacionIds, $periodoId);

        if ($modificaciones->isEmpty()) {
            throw new RuntimeException('No hay modificaciones pendientes para los elementos seleccionados.');
        }

        $fechaDesde = Carbon::parse($modificaciones->min('created_at'))->toDateString();
        $fechaHasta = Carbon::parse($modificaciones->max('created_at'))->toDateString();

        $grupos = $this->agruparPorAreaYTipoDoc($modificaciones);
        $config = ConfiguracionInstitucional::getAll();

        $generacion = DB::transaction(fn () => GeneracionModificacion::create([
            'periodo_id'      => $periodoId,
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'numero_oficio'   => $numeroOficio,
            'generado_por'    => $generadoPor->id,
            'generado_at'     => now(),
            'total_documentos'=> 0,
        ]));

        $carpeta = "modificaciones/{$generacion->id}";
        Storage::disk('local')->makeDirectory($carpeta);

        try {
            $documentosData  = [];
            $todosIdsDocumentados = [];

            foreach ($grupos as $areaId => $tiposDoc) {
                $area = Area::find($areaId);
                if (!$area) continue;

                foreach ($tiposDoc as $tipoDoc => $mods) {
                    $ruta = $this->generarDocumento($area, $tipoDoc, $mods, $config, $numeroOficio, $carpeta);

                    $documentosData[] = [
                        'generacion_id'       => $generacion->id,
                        'area_id'             => $areaId,
                        'tipo_documento'      => $tipoDoc,
                        'nombre_archivo'      => basename($ruta),
                        'ruta'                => $ruta,
                        'modificaciones_count'=> $mods->count(),
                        'ids_modificaciones'  => $mods->pluck('id')->all(),
                    ];

                    $todosIdsDocumentados = array_merge($todosIdsDocumentados, $mods->pluck('id')->all());
                }
            }

            DB::transaction(function () use ($generacion, $documentosData, $todosIdsDocumentados) {
                foreach ($documentosData as $doc) {
                    $ids = $doc['ids_modificaciones'];
                    unset($doc['ids_modificaciones']);

                    $docModel = DocumentoModificacionArea::create($doc);
                    $docModel->modificaciones()->attach($ids);
                }

                $generacion->update(['total_documentos' => count($documentosData)]);
                $this->modificacionRepository->marcarDocumentados($todosIdsDocumentados);
            });

        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($carpeta);
            $generacion->delete();
            throw $e;
        }

        return $generacion->load(['documentos.area', 'generadoPor', 'periodo']);
    }

    /**
     * Genera y devuelve la ruta local de un ZIP con todos los documentos de una generación.
     */
    public function generarZip(GeneracionModificacion $generacion): string
    {
        $generacion->loadMissing('documentos');

        $zipPath = Storage::disk('local')->path("modificaciones/temp_{$generacion->id}.zip");

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($generacion->documentos as $doc) {
            if (Storage::disk('local')->exists($doc->ruta)) {
                $zip->addFile(Storage::disk('local')->path($doc->ruta), $doc->nombre_archivo);
            }
        }

        $zip->close();

        return $zipPath;
    }

    // ─── Agrupación ──────────────────────────────────────────────────────────

    /**
     * Agrupa Collection<ModificacionProgramacion> por area_id → tipo_documento.
     * @return array<string, array<string, Collection>>
     */
    private function agruparPorAreaYTipoDoc(Collection $modificaciones): array
    {
        $porArea = [];

        foreach ($modificaciones as $mod) {
            $areaId = $mod->programacion?->curso?->area_id;
            if (!$areaId) continue;

            $tipoDoc = $this->resolverTipoDocumento($mod->tipo);
            $porArea[$areaId][$tipoDoc][] = $mod;
        }

        // Para cada área: si hay cerrar_curso Y abrir_seccion, fusionarlos en cierre_apertura
        $resultado = [];
        foreach ($porArea as $areaId => $tiposDoc) {
            $tieneCierre   = isset($tiposDoc['cierre']);
            $tieneApertura = isset($tiposDoc['cierre_apertura'])
                && collect($tiposDoc['cierre_apertura'])->where('tipo', 'abrir_seccion')->isNotEmpty();

            // Si hay cierres Y aperturas por separado, combinarlos en cierre_apertura
            if ($tieneCierre && $tieneApertura) {
                $combinados = array_merge(
                    $tiposDoc['cierre'],
                    $tiposDoc['cierre_apertura']
                );
                unset($tiposDoc['cierre'], $tiposDoc['cierre_apertura']);
                $tiposDoc['cierre_apertura'] = $combinados;
            }

            foreach ($tiposDoc as $tipo => $mods) {
                $resultado[$areaId][$tipo] = collect($mods);
            }
        }

        return $resultado;
    }

    private function resolverTipoDocumento(string $tipoMod): string
    {
        if (in_array($tipoMod, self::TIPOS_CIERRE))   return 'cierre';
        if (in_array($tipoMod, self::TIPOS_APERTURA)) return 'cierre_apertura';
        if (in_array($tipoMod, self::TIPOS_FUSION))   return 'fusion';
        return 'cambio_aula';
    }

    // ─── Generación del documento ─────────────────────────────────────────────

    private function generarDocumento(
        Area       $area,
        string     $tipoDoc,
        Collection $mods,
        array      $config,
        string     $numeroOficio,
        string     $carpeta
    ): string {
        $plantilla = PlantillaModificacion::where('tipo', $tipoDoc)->first();

        if (!$plantilla) {
            throw new RuntimeException("No hay plantilla cargada para el tipo '{$this->labelTipoDoc($tipoDoc)}'. Súbela en Configuración → Plantillas.");
        }

        $plantillaPath = Storage::disk('local')->path($plantilla->ruta);

        if (!file_exists($plantillaPath)) {
            throw new RuntimeException("El archivo de plantilla para '{$tipoDoc}' no se encontró en el servidor.");
        }

        $processor = new TemplateProcessor($plantillaPath);

        // ── Variables comunes ─────────────────────────────────────────────────
        $ciudad = $config['ciudad'] ?? 'Piura';
        $lema   = $config['anio_lema'] ?? '';

        $processor->setValue('ANIO_LEMA',      $lema);
        $processor->setValue('CIUDAD_FECHA',   "{$ciudad}, {$this->formatFecha(now())}");
        $processor->setValue('NUMERO_OFICIO',  "OFICIO CIRC. Nº {$numeroOficio}");
        $processor->setValue('TITULO_DIRECTOR', $area->titulo_director ?? 'Doctor');
        $processor->setValue('NOMBRE_DIRECTOR', mb_strtoupper($area->director_nombre ?? '', 'UTF-8'));
        $processor->setValue('CARGO_DIRECTOR',  $area->director_cargo ?? 'Director del Departamento Académico');
        $processor->setValue('AREA_NOMBRE',     mb_strtoupper($area->nombre_tabla ?? $area->nombre ?? '', 'UTF-8'));

        $titulo = $config['secretario_titulo'] ?? 'Dr.';
        $nombre = $config['secretario_nombre'] ?? '';
        $processor->setValue('SECRETARIO_TITULO_NOMBRE', "{$titulo} {$nombre}");
        $processor->setValue('SECRETARIO_CARGO',         $config['secretario_cargo'] ?? 'Secretario Académico');
        $processor->setValue('INSTITUCION_FIRMA',        $config['institucion_firma'] ?? '');

        // ── Filas de tabla según tipo ─────────────────────────────────────────
        match ($tipoDoc) {
            'cierre'         => $this->llenarTablaCierre($processor, $mods->filter(fn ($m) => $m->tipo === 'cerrar_curso')),
            'cierre_apertura'=> $this->llenarTablaCierreApertura($processor, $mods),
            'fusion'         => $this->llenarTablaFusion($processor, $mods),
            'cambio_aula'    => $this->llenarTablaCambioAula($processor, $mods),
        };

        // ── Guardar ───────────────────────────────────────────────────────────
        $nombreSafe    = Str::slug($area->nombre ?? 'area');
        $nombreArchivo = "{$tipoDoc}_{$nombreSafe}.docx";
        $rutaRelativa  = "{$carpeta}/{$nombreArchivo}";
        $rutaAbsoluta  = Storage::disk('local')->path($rutaRelativa);

        $processor->saveAs($rutaAbsoluta);

        return $rutaRelativa;
    }

    // ─── Llenado de tablas por tipo de documento ─────────────────────────────

    private function llenarTablaCierre(TemplateProcessor $p, Collection $mods): void
    {
        $count = $mods->count();
        $p->cloneRow('ITEM_C', $count);

        $i = 1;
        foreach ($mods as $mod) {
            $prog  = $mod->programacion;
            $antes = $mod->datos_anteriores;

            $p->setValue("ITEM_C#{$i}",      (string) $i);
            $p->setValue("CODIGO_C#{$i}",    $prog?->curso?->codigo ?? '');
            $p->setValue("CURSO_C#{$i}",     mb_strtoupper($prog?->curso?->nombre ?? '', 'UTF-8'));
            $p->setValue("GRUPO_C#{$i}",     $prog?->grupo ?? '');
            $p->setValue("SECCION_C#{$i}",   $prog?->seccion ?? '');
            $p->setValue("AULA_C#{$i}",      $antes['aula_nombre'] ?? '');
            $p->setValue("INSCRITOS_C#{$i}", (string) ($antes['n_inscritos'] ?? ''));
            $p->setValue("MOTIVO_C#{$i}",    $mod->motivo);
            $i++;
        }
    }

    private function llenarTablaCierreApertura(TemplateProcessor $p, Collection $mods): void
    {
        $cierres   = $mods->filter(fn ($m) => $m->tipo === 'cerrar_curso');
        $aperturas = $mods->filter(fn ($m) => $m->tipo === 'abrir_seccion');

        // Tabla cierres
        $countC = max($cierres->count(), 1);
        $p->cloneRow('ITEM_C', $countC);
        $i = 1;
        foreach ($cierres as $mod) {
            $prog  = $mod->programacion;
            $antes = $mod->datos_anteriores;
            $p->setValue("ITEM_C#{$i}",      (string) $i);
            $p->setValue("CODIGO_C#{$i}",    $prog?->curso?->codigo ?? '');
            $p->setValue("CURSO_C#{$i}",     mb_strtoupper($prog?->curso?->nombre ?? '', 'UTF-8'));
            $p->setValue("GRUPO_C#{$i}",     $prog?->grupo ?? '');
            $p->setValue("AULA_C#{$i}",      $antes['aula_nombre'] ?? '');
            $p->setValue("INSCRITOS_C#{$i}", (string) ($antes['n_inscritos'] ?? ''));
            $p->setValue("MOTIVO_C#{$i}",    $mod->motivo);
            $i++;
        }
        if ($cierres->isEmpty()) {
            $p->setValue('ITEM_C#1', ''); $p->setValue('CODIGO_C#1', '');
            $p->setValue('CURSO_C#1', ''); $p->setValue('GRUPO_C#1', '');
            $p->setValue('AULA_C#1', ''); $p->setValue('INSCRITOS_C#1', '');
            $p->setValue('MOTIVO_C#1', '');
        }

        // Tabla aperturas
        $countA = max($aperturas->count(), 1);
        $p->cloneRow('ITEM_A', $countA);
        $i = 1;
        foreach ($aperturas as $mod) {
            $prog    = $mod->programacion;
            $despues = $mod->datos_nuevos;
            $p->setValue("ITEM_A#{$i}",    (string) $i);
            $p->setValue("CODIGO_A#{$i}",  $prog?->curso?->codigo ?? '');
            $p->setValue("CURSO_A#{$i}",   mb_strtoupper($prog?->curso?->nombre ?? '', 'UTF-8'));
            $p->setValue("GRUPO_A#{$i}",   $despues['grupo'] ?? $prog?->grupo ?? '');
            $p->setValue("AULA_A#{$i}",    $despues['aula_nombre'] ?? '');
            $p->setValue("CAP_A#{$i}",     (string) ($despues['capacidad'] ?? ''));
            $p->setValue("MOTIVO_A#{$i}",  $mod->motivo);
            $i++;
        }
        if ($aperturas->isEmpty()) {
            $p->setValue('ITEM_A#1', ''); $p->setValue('CODIGO_A#1', '');
            $p->setValue('CURSO_A#1', ''); $p->setValue('GRUPO_A#1', '');
            $p->setValue('AULA_A#1', ''); $p->setValue('CAP_A#1', '');
            $p->setValue('MOTIVO_A#1', '');
        }
    }

    private function llenarTablaFusion(TemplateProcessor $p, Collection $mods): void
    {
        $p->cloneRow('ITEM_F', $mods->count());
        $i = 1;
        foreach ($mods as $mod) {
            $prog    = $mod->programacion;
            $antes   = $mod->datos_anteriores;
            $despues = $mod->datos_nuevos;

            $seccionesOrigen = collect($antes['secciones_origen'] ?? [])
                ->map(fn ($s) => ($s['grupo'] ?? '') . ' (' . ($s['aula_nombre'] ?? 'sin aula') . ')')
                ->implode(', ');

            $p->setValue("ITEM_F#{$i}",       (string) $i);
            $p->setValue("CODIGO_F#{$i}",     $prog?->curso?->codigo ?? '');
            $p->setValue("CURSO_F#{$i}",      mb_strtoupper($prog?->curso?->nombre ?? '', 'UTF-8'));
            $p->setValue("ORIGEN_F#{$i}",     $seccionesOrigen);
            $p->setValue("DESTINO_F#{$i}",    ($despues['grupo'] ?? '') . ' (' . ($despues['aula_nombre'] ?? 'sin aula') . ')');
            $p->setValue("MOTIVO_F#{$i}",     $mod->motivo);
            $i++;
        }
    }

    private function llenarTablaCambioAula(TemplateProcessor $p, Collection $mods): void
    {
        $p->cloneRow('ITEM_CA', $mods->count());
        $i = 1;
        foreach ($mods as $mod) {
            $prog    = $mod->programacion;
            $antes   = $mod->datos_anteriores;
            $despues = $mod->datos_nuevos;

            $p->setValue("ITEM_CA#{$i}",        (string) $i);
            $p->setValue("CODIGO_CA#{$i}",       $prog?->curso?->codigo ?? '');
            $p->setValue("CURSO_CA#{$i}",        mb_strtoupper($prog?->curso?->nombre ?? '', 'UTF-8'));
            $p->setValue("GRUPO_CA#{$i}",        $prog?->grupo ?? '');
            $p->setValue("AULA_ANT_CA#{$i}",     $antes['aula_nombre'] ?? '');
            $p->setValue("AULA_NUE_CA#{$i}",     $despues['aula_nombre'] ?? '');
            $p->setValue("GRUPO_ANT_CA#{$i}",    $antes['grupo_horario_nombre'] ?? '');
            $p->setValue("GRUPO_NUE_CA#{$i}",    $despues['grupo_horario_nombre'] ?? '');
            $p->setValue("MOTIVO_CA#{$i}",       $mod->motivo);
            $i++;
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function plantillaExiste(string $tipo): bool
    {
        $plantilla = PlantillaModificacion::where('tipo', $tipo)->first();
        return $plantilla && Storage::disk('local')->exists($plantilla->ruta);
    }

    private function resumenModificacion(ModificacionProgramacion $mod): array
    {
        return [
            'id'          => $mod->id,
            'tipo'        => $mod->tipo,
            'curso_codigo'=> $mod->programacion?->curso?->codigo ?? '—',
            'curso_nombre'=> $mod->programacion?->curso?->nombre ?? '—',
            'seccion'     => $mod->programacion?->seccion ?? '—',
            'grupo'       => $mod->programacion?->grupo ?? '—',
            'motivo'      => $mod->motivo,
            'fecha'       => $mod->created_at?->toDateString(),
        ];
    }

    private function labelTipoDoc(string $tipo): string
    {
        return match ($tipo) {
            'cierre'         => 'Cierre de cursos',
            'cierre_apertura'=> 'Cierre y apertura de cursos',
            'fusion'         => 'Fusión de secciones',
            'cambio_aula'    => 'Cambio de aula / grupo',
            default          => $tipo,
        };
    }

    private function formatFecha(Carbon $fecha): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];
        return "{$fecha->day} de {$meses[$fecha->month]} del {$fecha->year}";
    }
}
