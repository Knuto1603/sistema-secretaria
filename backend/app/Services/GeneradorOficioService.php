<?php

namespace App\Services;

use App\Models\Area;
use App\Models\BorradorProgramacion;
use App\Models\ConfiguracionInstitucional;
use App\Models\DocumentoArea;
use App\Models\GeneracionDocumento;
use App\Models\PlanEstudios;
use App\Models\ProgramacionAcademica;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class GeneradorOficioService
{
    public function generar(
        BorradorProgramacion $borrador,
        string $numeroOficio,
        string $semestreTexto,
        User $generadoPor
    ): GeneracionDocumento {
        $config = ConfiguracionInstitucional::getAll();

        $programaciones = ProgramacionAcademica::where('periodo_id', $borrador->periodo_id)
            ->with([
                'curso.area',
                'aulaRelacion.pabellon',
                'escuelaProgramada',
                'escuelas',
            ])
            ->get();

        $htHpMap        = $this->buildHtHpMap($programaciones->pluck('curso_id')->unique()->toArray());
        $porArea        = $programaciones->groupBy(fn($p) => $p->curso?->area_id);
        $areasConCursos = $porArea->filter(fn($_, $k) => $k !== null);

        $areas = Area::findMany($areasConCursos->keys())->keyBy('id');

        $generacion = DB::transaction(function () use ($borrador, $numeroOficio, $semestreTexto, $generadoPor) {
            return GeneracionDocumento::create([
                'borrador_id'      => $borrador->id,
                'periodo_id'       => $borrador->periodo_id,
                'numero_oficio'    => $numeroOficio,
                'semestre_texto'   => $semestreTexto,
                'generado_por'     => $generadoPor->id,
                'generado_at'      => now(),
                'total_documentos' => 0,
            ]);
        });

        $carpeta = "documentos/{$borrador->id}/{$generacion->id}";
        Storage::disk('local')->makeDirectory($carpeta);

        try {
            $documentosData = [];

            foreach ($areasConCursos as $areaId => $cursosArea) {
                $area = $areas->get($areaId);
                if (!$area) continue;

                $ruta = $this->generarDocumento($area, $cursosArea, $htHpMap, $config, $numeroOficio, $semestreTexto, $carpeta);

                $documentosData[] = [
                    'generacion_id'  => $generacion->id,
                    'area_id'        => $area->id,
                    'nombre_archivo' => basename($ruta),
                    'ruta'           => $ruta,
                    'cursos_count'   => $cursosArea->count(),
                ];
            }

            DB::transaction(function () use ($generacion, $documentosData) {
                foreach ($documentosData as $doc) {
                    DocumentoArea::create($doc);
                }
                $generacion->update(['total_documentos' => count($documentosData)]);
            });

        } catch (\Throwable $e) {
            Storage::disk('local')->deleteDirectory($carpeta);
            $generacion->delete();
            throw $e;
        }

        return $generacion->load(['documentos.area', 'generadoPor', 'periodo']);
    }

    public function cursosSinArea(BorradorProgramacion $borrador): array
    {
        $programaciones = ProgramacionAcademica::where('periodo_id', $borrador->periodo_id)
            ->with('curso.area')
            ->get();

        return $programaciones
            ->filter(fn($p) => $p->curso && !$p->curso->area_id)
            ->map(fn($p) => ['codigo' => $p->curso->codigo, 'nombre' => $p->curso->nombre])
            ->unique('codigo')
            ->values()
            ->toArray();
    }

    // ─── Generación del documento usando plantilla ────────────────────────────

    private function generarDocumento(
        Area   $area,
        $programaciones,
        array  $htHpMap,
        array  $config,
        string $numeroOficio,
        string $semestre,
        string $carpeta
    ): string {
        $conAnexo      = $programaciones->count() > 5;
        $nombrePlantilla = $conAnexo ? 'plantilla-pa-anexo.docx' : 'plantilla-pa-inline.docx';
        $plantillaPath   = Storage::disk('local')->path("plantillas/{$nombrePlantilla}");

        if (!file_exists($plantillaPath)) {
            throw new \RuntimeException("Plantilla no encontrada: {$nombrePlantilla}");
        }

        $processor = new TemplateProcessor($plantillaPath);

        // ── Datos de la carta ─────────────────────────────────────────────────
        $ciudad = $config['ciudad'] ?? 'Piura';
        $lema   = $config['anio_lema'] ?? '';

        $processor->setValue('ANIO_LEMA',      $lema);
        $processor->setValue('CIUDAD_FECHA',   "{$ciudad}, {$this->formatFecha(now())}");
        $processor->setValue('NUMERO_OFICIO',  "OFICIO CIRC. Nº {$numeroOficio}");
        $processor->setValue('TITULO_DIRECTOR', $area->titulo_director ?? 'Doctor');
        $processor->setValue('NOMBRE_DIRECTOR', strtoupper($area->director_nombre ?? ''));
        $processor->setValue('CARGO_DIRECTOR',  $area->director_cargo ?? 'Director del Departamento Académico');
        $processor->setValue('SEMESTRE',        $semestre);
        $processor->setValue('AREA_NOMBRE',     strtoupper($area->nombre_tabla ?? $area->nombre ?? ''));

        $titulo = $config['secretario_titulo'] ?? 'Dr.';
        $nombre = $config['secretario_nombre'] ?? '';
        $processor->setValue('SECRETARIO_TITULO_NOMBRE', "{$titulo} {$nombre}");
        $processor->setValue('SECRETARIO_CARGO',         $config['secretario_cargo'] ?? 'Secretario Académico');
        $processor->setValue('INSTITUCION_FIRMA',        $config['institucion_firma'] ?? '');

        // ── Tabla de cursos ───────────────────────────────────────────────────
        $count = $programaciones->count();
        $processor->cloneRow('ITEM', $count);

        $item = 1;
        foreach ($programaciones as $prog) {
            $htHp    = $htHpMap[$prog->curso_id] ?? ['ht' => '', 'hp' => ''];
            $aula    = $this->getAulaNombre($prog);
            $grupo   = $prog->grupo ?? ($prog->grupoHorario?->nombre ?? '');
            $escuela = $prog->escuelaProgramada?->nombre_corto
                    ?? $prog->escuelas->first()?->nombre_corto
                    ?? '';

            $processor->setValue("ITEM#{$item}",    (string) $item);
            $processor->setValue("CODIGO#{$item}",  $prog->curso?->codigo ?? '');
            $processor->setValue("CURSO#{$item}",   strtoupper($prog->curso?->nombre ?? ''));
            $processor->setValue("HT#{$item}",      (string) ($htHp['ht'] ?? ''));
            $processor->setValue("HP#{$item}",      (string) ($htHp['hp'] ?? ''));
            $processor->setValue("GRUPO#{$item}",   (string) ($grupo));
            $processor->setValue("SECCION#{$item}", (string) ($prog->seccion ?? ''));
            $processor->setValue("AULA#{$item}",    $aula);
            $processor->setValue("ESCUELA#{$item}", strtoupper($escuela));

            $item++;
        }

        // ── Guardar ───────────────────────────────────────────────────────────
        $nombreSafe    = Str::slug($area->nombre ?? 'area');
        $nombreArchivo = "{$nombreSafe}.docx";
        $rutaRelativa  = "{$carpeta}/{$nombreArchivo}";
        $rutaAbsoluta  = Storage::disk('local')->path($rutaRelativa);

        $processor->saveAs($rutaAbsoluta);

        return $rutaRelativa;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildHtHpMap(array $cursoIds): array
    {
        $registros = PlanEstudios::whereIn('curso_id', $cursoIds)
            ->whereNotNull('horas_teoricas')
            ->select('curso_id', 'horas_teoricas', 'horas_practicas')
            ->get()
            ->keyBy('curso_id');

        $map = [];
        foreach ($cursoIds as $id) {
            $r = $registros->get($id);
            $map[$id] = [
                'ht' => $r?->horas_teoricas ?? '',
                'hp' => $r?->horas_practicas ?? '',
            ];
        }

        return $map;
    }

    private function getAulaNombre(ProgramacionAcademica $prog): string
    {
        $aulaTexto = $prog->getAttributes()['aula'] ?? null;
        if ($aulaTexto) {
            return (string) $aulaTexto;
        }

        $aulaRel = $prog->aulaRelacion;
        if (!$aulaRel) return '';

        $pabellon = $aulaRel->pabellon?->codigo ?? $aulaRel->pabellon?->nombre ?? '';
        return $pabellon ? "{$pabellon}-{$aulaRel->nombre}" : $aulaRel->nombre;
    }

    private function formatFecha(\DateTime|\Carbon\Carbon $fecha): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        $d = (int) $fecha->format('d');
        $m = (int) $fecha->format('m');
        $y = $fecha->format('Y');

        return "{$d} de {$meses[$m]} del {$y}";
    }
}
