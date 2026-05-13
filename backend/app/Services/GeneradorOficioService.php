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
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class GeneradorOficioService
{
    // TWIPs: 1cm ≈ 567, 1 inch = 1440
    private const MARGIN    = 1418; // 2.5cm
    private const FONT      = 'Times New Roman';
    private const FONT_SIZE = 12;

    // Ancho de página útil en TWIPs (A4 21cm - 5cm márgenes = 16cm = 9072)
    private const PAGE_WIDTH = 9072;

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

        // Pre-cargar todas las áreas necesarias en una sola query (evita N+1)
        $areas = Area::findMany($areasConCursos->keys())->keyBy('id');

        // Crear el registro de generación primero para obtener el ID del directorio
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
            // Si algo falla, limpiar archivos en disco para no dejar huérfanos
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

    // ─── Construcción del documento Word ─────────────────────────────────────

    private function generarDocumento(
        Area   $area,
        $programaciones,
        array  $htHpMap,
        array  $config,
        string $numeroOficio,
        string $semestre,
        string $carpeta
    ): string {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName(self::FONT);
        $phpWord->setDefaultFontSize(self::FONT_SIZE);

        $conAnexo = $programaciones->count() > 5;

        // ── Página de carta ──────────────────────────────────────────────────
        $section = $phpWord->addSection([
            'marginTop'    => self::MARGIN,
            'marginBottom' => self::MARGIN,
            'marginLeft'   => self::MARGIN,
            'marginRight'  => self::MARGIN,
            'paperSize'    => 'A4',
        ]);

        $this->addEncabezadoInstitucional($section, $config);
        $this->addLema($section, $config);
        $this->addFechaYOficio($section, $config, $numeroOficio);
        $this->addDestinatario($section, $area);
        $this->addAsunto($section, $semestre);
        $this->addCuerpo($section, $semestre, $conAnexo);

        if (!$conAnexo) {
            $section->addTextBreak(1);
            $this->addTabla($section, $programaciones, $htHpMap, false);
            $section->addTextBreak(1);
        }

        $this->addCierre($section, $semestre, $conAnexo);
        $this->addFirma($section, $config);
        $this->addDistribucion($section);

        // ── Página de anexo (>5 cursos) ──────────────────────────────────────
        if ($conAnexo) {
            $sectionAnexo = $phpWord->addSection([
                'marginTop'    => self::MARGIN,
                'marginBottom' => self::MARGIN,
                'marginLeft'   => self::MARGIN,
                'marginRight'  => self::MARGIN,
                'paperSize'    => 'A4',
                'breakType'    => 'nextPage',
            ]);

            $this->addEncabezadoAnexo($sectionAnexo, $config, $semestre, $area);
            $sectionAnexo->addTextBreak(1);
            $this->addTabla($sectionAnexo, $programaciones, $htHpMap, true);
        }

        // Guardar
        $nombreSafe = Str::slug($area->nombre ?? 'area');
        $nombreArchivo = "{$nombreSafe}.docx";
        $rutaRelativa  = "{$carpeta}/{$nombreArchivo}";
        $rutaAbsoluta  = Storage::disk('local')->path($rutaRelativa);

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($rutaAbsoluta);

        return $rutaRelativa;
    }

    // ─── Secciones del documento ─────────────────────────────────────────────

    private function addEncabezadoInstitucional($section, array $config): void
    {
        $bold14 = ['bold' => true, 'size' => 14, 'name' => self::FONT];
        $bold12 = ['bold' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT];

        $section->addText($config['universidad'] ?? 'UNIVERSIDAD NACIONAL DE PIURA',
            $bold14, ['alignment' => Jc::CENTER]);
        $section->addText($config['facultad'] ?? 'FACULTAD DE INGENIERÍA INDUSTRIAL',
            $bold12, ['alignment' => Jc::CENTER]);
        $section->addText($config['dependencia'] ?? 'SECRETARIA ACADEMICA',
            $bold12, ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
    }

    private function addLema($section, array $config): void
    {
        $lema = $config['anio_lema'] ?? '';
        $section->addText("\"{$lema}\"",
            ['italic' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT],
            ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
    }

    private function addFechaYOficio($section, array $config, string $numeroOficio): void
    {
        $ciudad = $config['ciudad'] ?? 'Piura';
        $fecha  = $this->formatFecha(now());

        $section->addText("{$ciudad}, {$fecha}",
            ['size' => self::FONT_SIZE, 'name' => self::FONT],
            ['alignment' => Jc::RIGHT]);
        $section->addText("OFICIO CIRC. Nº {$numeroOficio}",
            ['bold' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT]);
        $section->addTextBreak(1);
    }

    private function addDestinatario($section, Area $area): void
    {
        $titulo = $area->titulo_director ?? 'Doctor';
        $nombre = strtoupper($area->director_nombre ?? '');
        $cargo  = $area->director_cargo ?? 'Director del Departamento Académico';

        $normal = ['size' => self::FONT_SIZE, 'name' => self::FONT];
        $bold   = ['bold' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT];

        $section->addText($titulo, $normal);
        $section->addText($nombre, $bold);
        $section->addText($cargo, $normal);
        $section->addText('Presente.-', $normal);
        $section->addTextBreak(1);
    }

    private function addAsunto($section, string $semestre): void
    {
        $section->addText(
            "ASUNTO: ASIGNACIÓN DE DOCENTE PROGRAMACIÓN ACADÉMICA {$semestre}.",
            ['bold' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT]
        );
        $section->addTextBreak(1);
    }

    private function addCuerpo($section, string $semestre, bool $conAnexo): void
    {
        $parStyle = ['alignment' => Jc::BOTH, 'spaceAfter' => 0];
        $font     = ['size' => self::FONT_SIZE, 'name' => self::FONT];

        if ($conAnexo) {
            $texto = "Tengo a bien dirigirme a usted, para saludar y hacer llegar anexo "
                   . "al documento, la relación de los cursos de la Programación Académica "
                   . "que serán dictados por su Departamento Académico en el semestre "
                   . "académico {$semestre}.";
        } else {
            $texto = "Tengo a bien dirigirme a usted, para saludar y hacerle llegar la relación "
                   . "de los cursos de la Programación Académica que serán dictados por su "
                   . "Departamento Académico en el semestre académico {$semestre}:";
        }

        $section->addText($texto, $font, $parStyle);
    }

    private function addCierre($section, string $semestre, bool $conAnexo): void
    {
        $parStyle = ['alignment' => Jc::BOTH, 'spaceAfter' => 0];
        $font     = ['size' => self::FONT_SIZE, 'name' => self::FONT];

        if ($conAnexo) {
            $cierre = "Agradecería a usted se sirva alcanzarnos, el nombre del docente que "
                    . "tendrán a cargo el dictado de los cursos en el semestre académico {$semestre}.";
        } else {
            $cierre = "Agradecería a usted se sirva alcanzarnos, los nombres de los docentes "
                    . "que tendrían a cargo el dictado de los cursos para el presente semestre "
                    . "académico {$semestre}.";
        }

        $section->addText($cierre, $font, $parStyle);
        $section->addTextBreak(1);
        $section->addText('Sin otro particular, me despido de usted.',
            $font, ['spaceAfter' => 0]);
        $section->addTextBreak(1);
        $section->addText('Atentamente.', $font);
    }

    private function addFirma($section, array $config): void
    {
        $font = ['size' => self::FONT_SIZE, 'name' => self::FONT];
        $bold = ['bold' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT];

        $section->addTextBreak(3);
        $titulo = $config['secretario_titulo'] ?? 'Dr.';
        $nombre = $config['secretario_nombre'] ?? '';
        $cargo  = $config['secretario_cargo'] ?? 'Secretario Académico';
        $inst   = $config['institucion_firma'] ?? '';

        $section->addText("{$titulo} {$nombre}", $bold);
        $section->addText($cargo, $font);
        $section->addText($inst, $font);
    }

    private function addDistribucion($section): void
    {
        $font = ['size' => self::FONT_SIZE, 'name' => self::FONT];
        $section->addTextBreak(1);
        $section->addText('Dist.:  Dptos. Académicos', $font);
        $section->addText('c.c.  : Archivo', $font);
    }

    private function addEncabezadoAnexo($section, array $config, string $semestre, Area $area): void
    {
        $bold = ['bold' => true, 'size' => self::FONT_SIZE, 'name' => self::FONT];
        $center = ['alignment' => Jc::CENTER];

        $section->addText($config['facultad'] ?? 'FACULTAD DE INGENIERÍA INDUSTRIAL', $bold, $center);
        $section->addText("PROGRAMACIÓN ACADÉMICA {$semestre}", $bold, $center);
        $section->addText('ÁREA DE ' . strtoupper($area->nombre_tabla ?? $area->nombre), $bold, $center);
    }

    // ─── Tabla de cursos ─────────────────────────────────────────────────────

    private function addTabla($section, $programaciones, array $htHpMap, bool $conEscuela): void
    {
        $tableStyle = [
            'borderSize'  => 4,
            'borderColor' => '000000',
            'cellMargin'  => 80,
            'width'       => self::PAGE_WIDTH,
            'unit'        => 'dxa',
        ];

        $cellHead = ['bgColor' => 'D9D9D9'];
        $fontHead = ['bold' => true, 'size' => 10, 'name' => self::FONT];
        $fontData = ['size' => 10, 'name' => self::FONT];
        $center   = ['alignment' => Jc::CENTER];

        // Anchos por columna en TWIPs
        if ($conEscuela) {
            $cols = [350, 800, 2450, 380, 380, 550, 600, 950, 1612];
        } else {
            $cols = [380, 900, 2900, 430, 430, 600, 680, 1752];
        }

        $table = $section->addTable($tableStyle);

        // Encabezado
        $table->addRow(300);
        $headers = $conEscuela
            ? ['ITEM', 'CÓDIGO', 'CURSO', 'H.T', 'H.P', 'GRUPO', 'SECCIÓN', 'AULA', 'ESCUELA']
            : ['ITEM', 'CÓDIGO', 'CURSO', 'H.T', 'H.P', 'GRUPO', 'SECCIÓN', 'AULA'];

        foreach ($headers as $i => $h) {
            $table->addCell($cols[$i], $cellHead)->addText($h, $fontHead, $center);
        }

        // Filas de datos
        $item = 1;
        foreach ($programaciones as $prog) {
            $htHp   = $htHpMap[$prog->curso_id] ?? ['ht' => '', 'hp' => ''];
            $aula   = $this->getAulaNombre($prog);
            $grupo  = $prog->grupo ?? ($prog->grupoHorario?->nombre ?? '');
            $escuela = $prog->escuelaProgramada?->nombre_corto
                    ?? $prog->escuelas->first()?->nombre_corto
                    ?? '';

            $table->addRow();
            $table->addCell($cols[0])->addText((string) $item++, $fontData, $center);
            $table->addCell($cols[1])->addText($prog->curso?->codigo ?? '', $fontData, $center);
            $table->addCell($cols[2])->addText(strtoupper($prog->curso?->nombre ?? ''), $fontData);
            $table->addCell($cols[3])->addText((string)($htHp['ht'] ?? ''), $fontData, $center);
            $table->addCell($cols[4])->addText((string)($htHp['hp'] ?? ''), $fontData, $center);
            $table->addCell($cols[5])->addText((string)($grupo), $fontData, $center);
            $table->addCell($cols[6])->addText((string)($prog->seccion ?? ''), $fontData, $center);
            $table->addCell($cols[7])->addText($aula, $fontData, $center);

            if ($conEscuela) {
                $table->addCell($cols[8])->addText(strtoupper($escuela), $fontData, $center);
            }
        }
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
        // Usar getAttributes() para leer la columna texto sin activar la relación aula()
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
