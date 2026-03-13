<?php

namespace App\Imports;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\ProgramacionAcademica;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Importa inscripciones de alumnos por curso desde el reporte HTML del SIGA
 * ("ALUMNOS POR CURSO XXXXXX.htm" — un <TABLE> por curso).
 *
 * Estrategia de matching (en orden):
 *  1. clave SIGA + periodo (nombre exacto)
 *  2. código de curso + periodo (nombre exacto)
 *  3. código de curso + periodo (búsqueda parcial por año-semestre)
 */
class InscripcionesHtmlImport
{
    private int   $programacionesProcesadas  = 0;
    private int   $inscripcionesCreadas      = 0;
    private int   $inscripcionesActualizadas = 0;
    private int   $alumnosNuevos             = 0;
    private array $noEncontrados             = [];
    private array $errores                   = [];

    private ?Role $rolEstudiante = null;

    public function import(string $filePath): void
    {
        $this->rolEstudiante = Role::where('name', 'estudiante')
                                   ->where('guard_name', 'web')
                                   ->first();

        $content = file_get_contents($filePath);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();

        $xpath  = new DOMXPath($dom);
        $tables = $xpath->query('//body/table');

        foreach ($tables as $table) {
            $this->procesarTabla($table, $xpath);
        }
    }

    private function procesarTabla(\DOMElement $table, DOMXPath $xpath): void
    {
        $clave       = null;
        $semestre    = null;
        $cursoCodigo = null;
        $alumnos     = [];

        $rows = $xpath->query('.//tr', $table);

        foreach ($rows as $tr) {
            $text = preg_replace('/\s+/', ' ', trim($tr->textContent));
            if ($text === '') {
                continue;
            }

            // Extraer SEMESTRE: "SEMESTRE: 2026-0" o "SEMESTRE : 2026-0"
            if (preg_match('/SEMESTRE\s*:\s*(\S+)/i', $text, $m)) {
                $semestre = trim($m[1]);
            }

            // Extraer código de CURSO: "CURSO : AL4401 - ..." o "CURSO: AL4401"
            if (preg_match('/CURSO\s*:\s*([A-Z]{2,3}\d{3,5})/i', $text, $m)) {
                $cursoCodigo = strtoupper(trim($m[1]));
            }

            // Extraer CLAVE: "CLAVE : 5081 SECCION : ..."
            if (preg_match('/CLAVE\s*:\s*(\d+)/i', $text, $m)) {
                $clave = trim($m[1]);
            }

            // Detectar fila de alumno: empieza con 10 dígitos
            if (preg_match('/^(\d{10})\s+(.+)$/', $text, $m)) {
                $alumnos[] = [
                    'codigo' => $m[1],
                    'nombre' => mb_convert_case(trim($m[2]), MB_CASE_TITLE, 'UTF-8'),
                ];
            }
        }

        if (empty($alumnos) || !$semestre) {
            return;
        }

        // Buscar la programación usando la estrategia de múltiples niveles
        $programacion = $this->buscarProgramacion($clave, $cursoCodigo, $semestre);

        if (!$programacion) {
            $label = $cursoCodigo ?? "CLAVE {$clave}";
            $this->noEncontrados[] = "{$label} (semestre {$semestre}): no se encontró programación académica.";
            return;
        }

        $this->guardarInscripciones($programacion, $alumnos);
    }

    /**
     * Busca la programación académica con múltiples estrategias de matching.
     */
    private function buscarProgramacion(?string $clave, ?string $cursoCodigo, string $semestre): ?ProgramacionAcademica
    {
        // ── Estrategia 1: clave SIGA + periodo exacto ──────────────────
        if ($clave) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $prog = ProgramacionAcademica::where('clave', $clave)
                    ->where('periodo_id', $periodo->id)
                    ->first();
                if ($prog) {
                    return $prog;
                }
            }
        }

        // ── Estrategia 2: código de curso + periodo exacto ─────────────
        if ($cursoCodigo) {
            $periodo = $this->buscarPeriodo($semestre);
            if ($periodo) {
                $curso = Curso::where('codigo', $cursoCodigo)->first();
                if ($curso) {
                    $candidatos = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->get();

                    if ($candidatos->count() === 1) {
                        return $candidatos->first();
                    }

                    // Si hay varias secciones y tenemos la clave, intentar afinar
                    if ($candidatos->count() > 1 && $clave) {
                        $porClave = $candidatos->firstWhere('clave', $clave);
                        if ($porClave) {
                            return $porClave;
                        }
                    }

                    // Retornar la primera si hay varias (mismo curso, mismo periodo)
                    if ($candidatos->count() > 0) {
                        return $candidatos->first();
                    }
                }
            }
        }

        // ── Estrategia 3: código de curso + búsqueda parcial de periodo ─
        if ($cursoCodigo) {
            $curso = Curso::where('codigo', $cursoCodigo)->first();
            if ($curso) {
                // Buscar periodos que contengan el semestre en el nombre
                $periodos = Periodo::where('nombre', 'like', "%{$semestre}%")->get();
                foreach ($periodos as $periodo) {
                    $prog = ProgramacionAcademica::where('curso_id', $curso->id)
                        ->where('periodo_id', $periodo->id)
                        ->first();
                    if ($prog) {
                        return $prog;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Busca el periodo por nombre exacto primero, luego parcial.
     */
    private function buscarPeriodo(string $semestre): ?Periodo
    {
        return Periodo::where('nombre', $semestre)->first()
            ?? Periodo::where('nombre', 'like', "%{$semestre}%")->first();
    }

    private function guardarInscripciones(ProgramacionAcademica $programacion, array $alumnos): void
    {
        $this->programacionesProcesadas++;

        DB::transaction(function () use ($programacion, $alumnos) {
            foreach ($alumnos as $alumnoData) {
                try {
                    $user = $this->crearOObtenerEstudiante($alumnoData['codigo'], $alumnoData['nombre']);

                    $result = Inscripcion::updateOrCreate(
                        [
                            'programacion_id' => $programacion->id,
                            'user_id'         => $user->id,
                        ],
                        [
                            'periodo_id' => $programacion->periodo_id,
                            'fuente'     => 'siga',
                        ]
                    );

                    if ($result->wasRecentlyCreated) {
                        $this->inscripcionesCreadas++;
                    } else {
                        $this->inscripcionesActualizadas++;
                    }
                } catch (\Throwable $e) {
                    $this->errores[] = "CLAVE {$programacion->clave} / {$alumnoData['codigo']}: " . $e->getMessage();
                }
            }

            // Recalcular n_inscritos
            $count = Inscripcion::where('programacion_id', $programacion->id)->count();
            $programacion->update(['n_inscritos' => $count]);
        });
    }

    private function crearOObtenerEstudiante(string $codigo, string $nombre): User
    {
        $existe = User::where('codigo_universitario', $codigo)->exists();

        $updates = [
            'tipo_usuario' => 'estudiante',
            'email'        => User::generarEmailEstudiante($codigo),
            'activo'       => true,
        ];
        if (!empty($nombre)) {
            $updates['name'] = $nombre;
        }

        $user = User::updateOrCreate(
            ['codigo_universitario' => $codigo],
            $updates
        );

        $user->asignarDatosDesdeCodigoUniversitario();

        if ($this->rolEstudiante && !$user->hasRole('estudiante')) {
            $user->assignRole($this->rolEstudiante);
        }

        if (!$existe) {
            $this->alumnosNuevos++;
        }

        return $user;
    }

    public function getResumen(): array
    {
        return [
            'programaciones_procesadas'  => $this->programacionesProcesadas,
            'inscripciones_creadas'      => $this->inscripcionesCreadas,
            'inscripciones_actualizadas' => $this->inscripcionesActualizadas,
            'alumnos_nuevos'             => $this->alumnosNuevos,
            'no_encontrados'             => count($this->noEncontrados),
            'detalle_no_encontrados'     => $this->noEncontrados,
            'errores'                    => count($this->errores),
            'detalle_errores'            => $this->errores,
        ];
    }
}
